<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Generation\Application\Command\RepairEchoExamples;
use App\Modules\Generation\Application\Command\RepairEchoExamplesHandler;
use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeExampleRegenerator;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(ExampleRegeneratorPort::class, new FakeExampleRegenerator());
});

/** Point a term's pinned example at [$sentence]; null removes it. */
function setExample(string $termId, ?string $sentence): void
{
    DB::table('term_examples')->where('term_id', $termId)->delete();
    if ($sentence === null) {
        return;
    }
    DB::table('term_examples')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'sentence' => $sentence,
        'sentence_translation' => 'перевод', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function exampleOf(string $termId): ?string
{
    /** @var string|null $sentence */
    $sentence = DB::table('term_examples')->where('term_id', $termId)->value('sentence');

    return $sentence;
}

/**
 * The dog-food collection as the acceptance found it: three phrases whose example is the phrase
 * itself, and the rest with real contextual examples.
 *
 * @return array{0: User, 1: string, 2: array<string, string>}  [owner, collectionId, text => termId]
 */
function echoDeck(): array
{
    $user = User::factory()->create();
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Buying Dog Food at the Store', new LanguageCode('ru'), new LanguageCode('en'),
    ))->value;

    $ids = [];
    $add = function (string $text, string $translation, ?string $example) use ($collectionId, $actor, &$ids): void {
        $id = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
            CollectionId::fromString($collectionId), $actor, $text, $translation, type: 'phrase',
        ))->value;
        setExample($id, $example);
        $ids[$text] = $id;
    };

    // The three the acceptance found: `lower(btrim(text)) = lower(btrim(sentence))`.
    $add('Where can I find dog food?', 'Где я могу найти корм для собак?', 'Where can I find dog food?');
    $add('Is this suitable for small breeds?', 'Подходит ли это для мелких пород?', 'Is this suitable for small breeds?');
    $add('How much does this bag cost?', 'Сколько стоит этот пакет?', 'How much does this bag cost?');
    // …and the ones that were fine.
    $add('do you have any discounts?', 'У вас есть скидки?', 'Do you have any discounts on dog food?');
    $add('Would you like a receipt?', 'Хотите чек?', 'Would you like a receipt with your purchase?');

    return [$user, $collectionId, $ids];
}

it('replaces every example that merely repeats its term, and leaves the good ones alone', function () {
    [$user, $collectionId, $ids] = echoDeck();

    $report = app(RepairEchoExamplesHandler::class)(new RepairEchoExamples(
        actorId: UserId::fromString($user->id),
        collectionId: CollectionId::fromString($collectionId),
    ));

    expect($report->examined)->toBe(5);
    expect($report->needingRepair)->toBe(3);
    expect($report->repaired())->toBe(3);
    expect($report->failures)->toBe([]);

    foreach (['Where can I find dog food?', 'Is this suitable for small breeds?', 'How much does this bag cost?'] as $text) {
        $example = exampleOf($ids[$text]);
        expect($example)->not->toBeNull();
        expect(mb_strtolower((string) $example))->not->toBe(mb_strtolower($text), "«{$text}» still echoes");
    }

    // Untouched: an example that CONTAINS the term is the correct case, not a defect.
    expect(exampleOf($ids['do you have any discounts?']))->toBe('Do you have any discounts on dog food?');
    expect(exampleOf($ids['Would you like a receipt?']))->toBe('Would you like a receipt with your purchase?');
});

it('repairs a term the validator left with NO example', function () {
    [$user, $collectionId, $ids] = echoDeck();
    setExample($ids['Would you like a receipt?'], null); // as an echo looks after being refused

    $report = app(RepairEchoExamplesHandler::class)(new RepairEchoExamples(
        actorId: UserId::fromString($user->id),
        collectionId: CollectionId::fromString($collectionId),
    ));

    expect($report->needingRepair)->toBe(4);
    expect(exampleOf($ids['Would you like a receipt?']))->not->toBeNull();
});

it('writes the spend to the ledger, one row per repaired term', function () {
    [$user, $collectionId] = echoDeck();

    $report = app(RepairEchoExamplesHandler::class)(new RepairEchoExamples(
        actorId: UserId::fromString($user->id),
        collectionId: CollectionId::fromString($collectionId),
    ));

    expect(DB::table('example_regenerations')->where('user_id', $user->id)->count())->toBe(3);
    expect($report->tokensIn)->toBe(90);   // the fake bills 30 in / 45 out per call
    expect($report->tokensOut)->toBe(135);
});

it('counts without spending in a dry run', function () {
    [$user, $collectionId, $ids] = echoDeck();

    $report = app(RepairEchoExamplesHandler::class)(new RepairEchoExamples(
        actorId: UserId::fromString($user->id),
        collectionId: CollectionId::fromString($collectionId),
        dryRun: true,
    ));

    expect($report->needingRepair)->toBe(3);
    expect(DB::table('example_regenerations')->count())->toBe(0);
    expect(exampleOf($ids['Where can I find dog food?']))->toBe('Where can I find dog food?', 'nothing was written');
});

it('is a no-op on a second run — a repaired term no longer echoes', function () {
    [$user, $collectionId] = echoDeck();
    $repair = app(RepairEchoExamplesHandler::class);
    $command = new RepairEchoExamples(
        actorId: UserId::fromString($user->id),
        collectionId: CollectionId::fromString($collectionId),
    );

    $repair($command);
    $second = $repair($command);

    expect($second->needingRepair)->toBe(0);
    expect(DB::table('example_regenerations')->count())->toBe(3, 'the retry spent nothing');
});

it('keeps going when one term call fails, and reports it', function () {
    [$user, $collectionId, $ids] = echoDeck();
    $failing = $ids['How much does this bag cost?'];
    $this->app->instance(ExampleRegeneratorPort::class, new class implements ExampleRegeneratorPort {
        public function regenerate(App\Modules\Generation\Application\Dto\ExampleRegenBrief $brief): App\Modules\Generation\Application\Dto\ExampleRegenResult
        {
            if (str_contains($brief->text, 'bag cost')) {
                throw new RuntimeException('OpenAI API error: 503');
            }

            return (new FakeExampleRegenerator())->regenerate($brief);
        }
    });

    $report = app(RepairEchoExamplesHandler::class)(new RepairEchoExamples(
        actorId: UserId::fromString($user->id),
        collectionId: CollectionId::fromString($collectionId),
    ));

    expect($report->repaired())->toBe(2, 'the other two are still repairable');
    expect($report->failures)->toHaveCount(1);
    expect($report->failures[0]['term_id'])->toBe($failing);
    expect(exampleOf($failing))->toBe('How much does this bag cost?', 'left as it was, not blanked');
});
