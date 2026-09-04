<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\SpeakCollectionLines;
use App\Modules\Generation\Application\Command\SpeakCollectionLinesHandler;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ОЗВУЧКА НА ПРОВОДЕ (наряд TTS-1, Ч.1.3).
 *
 * Контракт узкий и весь про одно: у реплики есть адрес файла — или его нет, и тогда клиент читает
 * её системным голосом, как читал всегда. Проверяется четыре вещи:
 *   посадка отдаёт озвучку ВСЕЙ сцены одним списком (телефон качает её на входе, а не по карточке);
 *   ход диалога несёт свой адрес;
 *   по адресу лежат байты;
 *   смена голоса в конфиге МЕНЯЕТ АДРЕС — значит старый файл на телефоне не может проиграться за
 *   новый голос, и это свойство схемы, а не дисциплины клиента.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
    config()->set('generation.speech.voices', [
        'en' => ['provider' => 'fake', 'model' => 'fake-tts', 'voice' => 'sable', 'speed' => 0.9],
    ]);
});

function voiceDayOne(object $ctx): array
{
    [$user, $token, $planId] = startedPlan($ctx);
    $collectionId = (string) DB::table('learning_plan_days')
        ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');

    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer());
    app(SpeakCollectionLinesHandler::class)(new SpeakCollectionLines(CollectionId::fromString($collectionId)));

    return [$user, $token, $planId];
}

it('hands the sitting every line it can play from a file, in one list', function () {
    [, $token, $planId] = voiceDayOne($this);

    $session = planSession($this, $token, $planId);

    // Списком, а не полем на карточке: качать надо ВСЮ посадку на входе в день, включая реплики
    // второго присеста и спасателей, которых сегодня может не быть ни на одной карточке.
    expect($session['line_audio'])->not->toBeEmpty();
    foreach ($session['line_audio'] as $row) {
        expect($row['text'])->not->toBe('')
            ->and($row['url'])->toContain('/api/v1/audio/lines/');
    }
});

it('marks a line with no server file as null rather than leaving the field out', function () {
    [, $token, $planId] = startedPlan($this); // ни одна реплика не озвучена

    $session = planSession($this, $token, $planId);

    expect($session['line_audio'])->toBe([]);
    foreach ($session['dialogues'] as $dialogue) {
        foreach ($dialogue['turns'] as $turn) {
            expect($turn)->toHaveKey('audio_url')
                ->and($turn['audio_url'])->toBeNull();
        }
    }
});

it('serves the bytes at the address the payload names', function () {
    [, $token, $planId] = voiceDayOne($this);

    $session = planSession($this, $token, $planId);
    $url = (string) $session['line_audio'][0]['url'];

    $response = $this->withHeader('Authorization', "Bearer {$token}")->get($url);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('audio/mpeg')
        // Адрес однозначен для пары (реплика, голос+темп), поэтому содержимое по нему измениться не
        // может: `immutable` здесь — правда о ресурсе, а не оптимизация.
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->getContent())->not->toBe('');
});

it('never plays the old cache for a new voice — the address moves with the voice', function () {
    [, $token, $planId] = voiceDayOne($this);

    // Файлы куплены голосом `sable`; пакет теперь называет `other`, и этого голоса ещё не куплено.
    expect(DB::table('term_audios')->count())->toBeGreaterThan(0);
    app()->instance(VoiceCatalog::class, new VoiceCatalog([
        'en' => ['provider' => 'fake', 'model' => 'fake-tts', 'voice' => 'other', 'speed' => 0.9],
    ]));

    // Посадка честно молчит вместо того, чтобы проиграть старый голос за новый: адрес файла — это
    // id строки, а строка ключуется голосом и темпом, поэтому «сыграть чужим голосом» негде.
    expect(planSession($this, $token, $planId)['line_audio'])->toBe([]);
});

it('puts the same address on the cheat sheet, so one line has one voice', function () {
    [, $token, $planId] = voiceDayOne($this);

    $terms = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")->assertOk()->json('data.terms');

    $voiced = array_values(array_filter($terms, static fn (array $t): bool => $t['audio_url'] !== null));

    // Шпаргалка читает вслух с экрана плана (канон §13). Два голоса на одну реплику — это две
    // разные реплики для уха.
    expect($voiced)->not->toBeEmpty();
    foreach ($voiced as $term) {
        expect($term['shelf'])->toBeIn(['hear', 'rescue'])
            ->and($term['audio_url'])->toContain('/api/v1/audio/lines/');
    }
});
