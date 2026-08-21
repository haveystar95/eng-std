<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermDescriptionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Give a term the one piece of content this trainer needs. */
function describeTerm(string $termId, string $text): void
{
    app(TermDescriptionWriter::class)->ensure(TermId::fromString($termId), 'en', $text);
}

/** Switch a trainer on for everybody (what the owner does from the admin panel). */
function enableModeGlobally(string $mode): void
{
    DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', $mode)->update(['enabled' => true]);
}

// ── the release rule ─────────────────────────────────────────────────────────

it('ships switched off, with a row on the assembly rung', function () {
    expect(config('learning.enabled_modes'))->not->toContain('description_match');

    $row = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'description_match')->first();

    expect($row)->not->toBeNull()
        ->and((bool) $row->enabled)->toBeFalse()
        ->and((string) $row->min_acquisition)->toBe('graduated')
        ->and($row->min_learning_step)->toBeNull()
        ->and($row->min_successful_reviews)->toBeNull()
        // `standard`, not `distant`: distant options exist for the recognition rungs, and this
        // card's options come from the distractor reader like an ordinary multiple_choice.
        ->and((string) $row->options_policy)->toBe('standard');
});

it('reaches the device through /sync as a matrix row, still switched off', function () {
    [, $token] = learner();
    $settings = sync($this, $token)['settings'];

    $row = collect($settings['mode_admission'])->firstWhere('mode', 'description_match');

    expect($row)->not->toBeNull()
        ->and($row['min_step'])->toBe(LearningLadder::STEP_ASSEMBLY)
        // The matrix says where it WOULD sit; the toggle list is what says nobody has it yet.
        ->and($settings['exercise_modes'])->not->toContain('description_match');
});

// ── the content gate ─────────────────────────────────────────────────────────

it('is never dealt to a term with no description, even when switched on', function () {
    enableModeGlobally('description_match');
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'invoice', 'счёт');
    // Somewhere for the distractor reader to find wrong options, outside this one-term collection.
    seedCollectionWith($user, 'ledger', 'книга учёта');
    answerTimes($this, $token, $termId, 'invoice', 6);

    expect(practiceModes($this, $token, $collectionId))->not->toContain('description_match');
});

it('is dealt once the term has a description, and asks WITH it', function () {
    enableModeGlobally('description_match');
    [$user, $token] = learner();
    // A ONE-TERM collection: that is what makes a practice session fan across every applicable
    // trainer instead of dealing one card off the round-robin.
    [$collectionId, $termId] = seedCollectionWith($user, 'invoice', 'счёт');
    // Wrong options live outside it — the distractor reader tops up from same-language terms.
    foreach ([['ledger', 'книга учёта'], ['receipt', 'чек'], ['deposit', 'вклад']] as [$text, $translation]) {
        seedCollectionWith($user, $text, $translation);
    }
    describeTerm($termId, 'A paper that says how much money you must pay for something.');
    answerTimes($this, $token, $termId, 'invoice', 6);

    $card = practiceCardFor($this, $token, $collectionId, 'description_match');

    expect($card)->not->toBeNull()
        // The description IS the question — not the translation, and not the example.
        ->and($card['prompt'])->toBe('A paper that says how much money you must pay for something.')
        // The answer stays the TERM and is graded as text: the options are words, so nothing here
        // needs identity grading (that exists for the rung-1 card, whose option is a translation).
        ->and($card['answer'])->toBe('invoice')
        ->and($card['option_ids'] ?? null)->toBeNull()
        ->and($card['options'])->toContain('invoice')
        ->and(count($card['options']))->toBeGreaterThanOrEqual(2);
});

it('grades a tapped option as text, exactly like an ordinary multiple_choice', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'invoice', 'счёт');
    describeTerm($termId, 'A paper that says how much money you must pay for something.');
    answerTimes($this, $token, $termId, 'invoice', 6);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(),
            'term_id' => $termId,
            'exercise_mode' => 'description_match',
            'response' => 'invoice',
            'answered_at' => now()->toIso8601String(),
            'client_seq' => 900,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    // Graded as TEXT against the term's own forms, exactly like an ordinary multiple_choice — and
    // the row survives the insert, which is what the widened column and the CHECK are for.
    $this->assertDatabaseHas('reviews', [
        'term_id' => $termId,
        'exercise_mode' => 'description_match',
        'is_correct' => true,
    ]);
});

it('caps at «good» — a four-way tap can never buy a month', function () {
    expect(ExerciseMode::DescriptionMatch->maxGrade()->value)->toBe('good')
        ->and(ExerciseMode::DescriptionMatch->forgivesTypos())->toBeFalse()
        ->and(ExerciseMode::DescriptionMatch->gradesAgainstExample(null))->toBeFalse();
});

/**
 * Every mode a ONE-TERM practice session deals for this collection — the honest read of «what would
 * this word be drilled in right now», through the real endpoint. A one-term collection is what
 * makes the session fan across every applicable trainer instead of dealing one card.
 *
 * @return list<string>
 */
function practiceModes(object $ctx, string $token, string $collectionId): array
{
    return array_values(array_unique(array_map(
        static fn (array $c): string => (string) $c['exercise_mode'],
        practiceCards($ctx, $token, $collectionId),
    )));
}

/** @return list<array<string, mixed>> */
function practiceCards(object $ctx, string $token, string $collectionId): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['practice' => true, 'collection_id' => $collectionId])
        ->assertOk()->json('data.cards');
}

/** @return array<string, mixed>|null */
function practiceCardFor(object $ctx, string $token, string $collectionId, string $mode): ?array
{
    foreach (practiceCards($ctx, $token, $collectionId) as $card) {
        if ($card['exercise_mode'] === $mode) {
            return $card;
        }
    }

    return null;
}
