<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\SpeakCollectionLines;
use App\Modules\Generation\Application\Command\SpeakCollectionLinesHandler;
use App\Modules\Generation\Application\Port\DispatchesLineSpeech;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Generation\Infrastructure\Job\SpeakLinesJob;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Vocabulary\Application\Port\TermAudioStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * СТАНОК ОЗВУЧКИ (наряд TTS-1, Ч.1).
 *
 * Четыре обещания, и все четыре про деньги или про день:
 *   озвучиваются ТОЛЬКО те полки, которые названы конфигом, — слова и связки остаются на системном
 *   голосе, и платить за них никто не начинает молча;
 *   та же реплика тем же голосом второй раз НЕ покупается — кэш общий на всех пользователей;
 *   отказ вендора на одной реплике не лишает голоса остальные, а день остаётся `ready`;
 *   выключенный тумблер не отправляет наружу ни одного байта.
 */
beforeEach(function (): void {
    fakePlanModel();
    config()->set('generation.speech.voices', [
        'en' => ['provider' => 'fake', 'model' => 'fake-tts', 'voice' => 'sable', 'speed' => 0.9],
    ]);
});

/** The day-1 collection of a fresh plan — the only place a plan's rescue kit lives. */
function dayOneCollection(object $ctx): string
{
    [, , $planId] = startedPlan($ctx);

    return (string) DB::table('learning_plan_days')
        ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');
}

function runSpeech(string $collectionId, FakeSpeechSynthesizer $speech, array $shelves = ['hear', 'rescue']): void
{
    app()->instance(SpeechSynthesizerPort::class, $speech);
    app()->when(SpeakCollectionLinesHandler::class)->needs('$shelves')->give(fn (): array => $shelves);

    app(SpeakCollectionLinesHandler::class)(new SpeakCollectionLines(CollectionId::fromString($collectionId)));
}

it('voices the shelves it was told to and no others', function () {
    $collectionId = dayOneCollection($this);
    runSpeech($collectionId, $speech = new FakeSpeechSynthesizer());

    $shelves = DB::table('term_audios')
        ->join('terms', 'terms.id', '=', 'term_audios.term_id')
        ->distinct()->pluck('terms.shelf')->sort()->values()->all();

    // Слова и связки платно не читаются: их девять десятых материала, а разницы на слух — на
    // одиночном слове — почти нет.
    expect($shelves)->toBe(['hear', 'rescue'])
        ->and($speech->calls)->toBe(DB::table('term_audios')->count());
});

it('does not buy the same line twice, however many times the job runs', function () {
    $collectionId = dayOneCollection($this);

    runSpeech($collectionId, $first = new FakeSpeechSynthesizer());
    $rows = DB::table('term_audios')->count();

    runSpeech($collectionId, $second = new FakeSpeechSynthesizer());

    // Идемпотентность держит `missingFor()`, а не память джобы: то же самое защищает второй план,
    // попросивший ту же фразу, — кэш общий на всех пользователей.
    expect($first->calls)->toBe($rows)
        ->and($second->calls)->toBe(0)
        ->and(DB::table('term_audios')->count())->toBe($rows);
});

it('writes the file beside the row, at the address the row names', function () {
    $collectionId = dayOneCollection($this);
    runSpeech($collectionId, new FakeSpeechSynthesizer());

    $row = DB::table('term_audios')->first();
    expect($row)->not->toBeNull();

    // Строка — это обещание, что файл есть. Обещание раньше файла читается сборкой посадки как
    // готовая озвучка, которой ещё нет, — то самое «тишина вместо голоса», которое канон запрещает.
    expect(app(TermAudioStore::class)->read((string) $row->path))->not->toBeNull()
        ->and((int) $row->bytes)->toBeGreaterThan(0);
});

it('keeps the day whole when the vendor refuses a line for good', function () {
    $collectionId = dayOneCollection($this);
    runSpeech($collectionId, new FakeSpeechSynthesizer(FakeSpeechSynthesizer::REFUSED));

    // Ни одной озвучки — и ни одного исключения наружу: день уже `ready`, и уронить его нечем.
    expect(DB::table('term_audios')->count())->toBe(0)
        ->and(DB::table('learning_plan_days')->where('collection_id', $collectionId)->value('status'))
        ->toBe('ready');
});

it('lets a retryable failure out, so the job can come back for it', function () {
    $collectionId = dayOneCollection($this);

    expect(fn () => runSpeech($collectionId, new FakeSpeechSynthesizer(FakeSpeechSynthesizer::RATE_LIMITED)))
        ->toThrow(TransientSpeechError::class);
});

it('says nothing at all to the vendor while the pipe is switched off', function () {
    Queue::fake();
    config()->set('generation.speech.enabled', false);

    app(DispatchesLineSpeech::class)->dispatch(CollectionId::generate());

    // Тумблер стоит на ВХОДЕ: «выключено» значит «никто никуда не ходил», а не «сходили и передумали».
    Queue::assertNotPushed(SpeakLinesJob::class);
});

it('queues the work once the pipe is switched on', function () {
    Queue::fake();
    config()->set('generation.speech.enabled', true);

    app(DispatchesLineSpeech::class)->dispatch(CollectionId::generate());

    Queue::assertPushed(SpeakLinesJob::class);
});

it('leaves the lines alone when the language pack has no voice', function () {
    config()->set('generation.speech.voices', []);
    $collectionId = dayOneCollection($this);

    runSpeech($collectionId, $speech = new FakeSpeechSynthesizer());

    expect($speech->calls)->toBe(0)
        ->and(DB::table('term_audios')->count())->toBe(0);
});
