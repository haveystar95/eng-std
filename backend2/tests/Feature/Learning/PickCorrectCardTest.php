<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const PC_RIGHT = 'Your workstation is ready for you.';

/** The pinned example plus the two wrong sentences the станок wrote against it. */
function pickCorrectContent(string $termId): void
{
    $exampleId = Ulid::generate();
    seedExample([
        'id' => $exampleId,
        'term_id' => $termId,
        'sentence' => PC_RIGHT,
        'translation' => 'Ваше рабочее место готово.',
        'source' => 'ai',
    ]);

    foreach ([
        ['Your workstation are ready for you.', 'tense', 'are', 'is'],
        ['Your workstation is ready of you.', 'preposition', 'of', 'for'],
    ] as [$sentence, $type, $span, $correction]) {
        DB::table('example_distractors')->insert([
            'id' => Ulid::generate(),
            'example_id' => $exampleId,
            'sentence' => $sentence,
            'error_type' => $type,
            'error_span' => $span,
            'correction' => $correction,
            'generator_version' => 'enrich-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/** The mode ships off, so every test opts in for its own user. */
function enablePickCorrectFor(string $userId): void
{
    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($userId),
        new EnabledModes([ExerciseMode::PickCorrect]),
    );
}

function pickCorrectCard(object $ctx, string $token, string $collectionId): array
{
    $response = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['id' => Ulid::generate(), 'collection_id' => $collectionId]);
    $response->assertOk();

    return $response->json('data.cards.0');
}

it('ships switched off — a fresh install never deals it', function () {
    expect(config('learning.enabled_modes'))->not->toContain('pick_correct');
});

it('builds a card of three sentences with the example translation as the prompt', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    enablePickCorrectFor($user->id);

    $card = pickCorrectCard($this, $token, $collectionId);

    expect($card['exercise_mode'])->toBe('pick_correct')
        // The question is the translation: "which of these says this correctly?"
        ->and($card['prompt'])->toBe('Ваше рабочее место готово.')
        // The answer is the example VERBATIM — the mode grades against the sentence.
        ->and($card['answer'])->toBe(PC_RIGHT)
        ->and($card['options'])->toHaveCount(3)
        ->and($card['options'])->toContain(PC_RIGHT);
});

it('carries the error span and correction for each WRONG option, and only for those', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    enablePickCorrectFor($user->id);

    $card = pickCorrectCard($this, $token, $collectionId);

    // This is the whole reason the mode beats multiple_choice: a wrong pick can be EXPLAINED.
    expect($card['option_feedback'])->toHaveCount(2);

    $bySentence = collect($card['option_feedback'])->keyBy('sentence');
    expect($bySentence['Your workstation are ready for you.']['error_span'])->toBe('are')
        ->and($bySentence['Your workstation are ready for you.']['correction'])->toBe('is')
        ->and($bySentence['Your workstation is ready of you.']['correction'])->toBe('for')
        // The right answer never appears in the feedback — there is nothing to underline on it.
        ->and($bySentence->has(PC_RIGHT))->toBeFalse();
});

it('never deals the mode to a term with fewer than two distractors', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    // The review deleted one as grammatical — the term loses the mode again.
    DB::table('example_distractors')->limit(1)->delete();
    // …and falls back to multiple_choice, which needs a neighbour to be buildable at all (QA-15).
    addWordTo($collectionId, $user->id, 'front desk', 'стойка регистрации');
    enablePickCorrectFor($user->id);

    $card = pickCorrectCard($this, $token, $collectionId);

    expect($card['exercise_mode'])->not->toBe('pick_correct');
});

it('never puts two distractors with the same error span on one card', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    // A third row breaking the SAME fragment as the first. Two options differing from the example in
    // the same place turn the card into "which spelling of `are` did we mean", and the underline
    // afterwards points at the same word whichever one was picked.
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(),
        'example_id' => DB::table('term_examples')->where('term_id', $termId)->value('id'),
        'sentence' => 'Your workstation ARE ready for you now.',
        'error_type' => 'tense',
        'error_span' => 'ARE',
        'correction' => 'is',
        'generator_version' => 'enrich-v1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    enablePickCorrectFor($user->id);

    $card = pickCorrectCard($this, $token, $collectionId);

    // Comparison is trim + lowercase, so `ARE` and `are` are the same span.
    $spans = array_map(static fn (array $f): string => mb_strtolower($f['error_span']), $card['option_feedback']);
    expect($card['exercise_mode'])->toBe('pick_correct')
        ->and($spans)->toHaveCount(2)
        ->and(array_unique($spans))->toHaveCount(2);
});

it('does not deal the mode when the second distractor only repeats the first span', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    // Two rows, one usable span: the gate has to count what a card can actually use, or it passes the
    // ≥2 check and then hands the assembler one wrong option — a two-option coin flip.
    DB::table('example_distractors')->where('error_span', 'of')->update(['error_span' => 'are', 'correction' => 'is']);
    // …and falls back to multiple_choice, which needs a neighbour to be buildable at all (QA-15).
    addWordTo($collectionId, $user->id, 'front desk', 'стойка регистрации');
    enablePickCorrectFor($user->id);

    $card = pickCorrectCard($this, $token, $collectionId);

    expect($card['exercise_mode'])->not->toBe('pick_correct');
});

it('grades a correct pick against the sentence, capped at good', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    enablePickCorrectFor($user->id);
    $card = pickCorrectCard($this, $token, $collectionId);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'pick_correct',
            // The client sends the chosen SENTENCE — no option indexes on the wire.
            'response' => PC_RIGHT, 'latency_ms' => 1200,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    // Fast and clean, but a three-way pick can never buy `easy`.
    expect(DB::table('reviews')->where('term_id', $termId)->value('is_correct'))->toBeTrue();
    expect($card['exercise_mode'])->toBe('pick_correct');
});

it('marks a wrong pick wrong', function () {
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'workstation', 'рабочее место');
    pickCorrectContent($termId);
    enablePickCorrectFor($user->id);
    pickCorrectCard($this, $token, $collectionId);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'pick_correct',
            'response' => 'Your workstation are ready for you.', 'latency_ms' => 3000,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk();

    expect(DB::table('reviews')->where('term_id', $termId)->value('is_correct'))->toBeFalse();
});
