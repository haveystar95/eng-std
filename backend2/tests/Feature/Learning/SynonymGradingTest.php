<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * SYN-1 Ч.3 — a near-synonym is a correct answer of the SAME strength, on the cards that asked what
 * the word means and on no others.
 */
function seedSynonym(string $termId, string $text, string $lang = 'en'): void
{
    DB::table('term_synonyms')->insert([
        'id' => (string) Ulid::generate(),
        'term_id' => $termId,
        'text' => $text,
        'lang' => $lang,
        'source' => 'auto',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Upload one answer in one mode and return the grade the server derived for it. */
function answerIn(object $ctx, string $token, string $termId, string $mode, string $response): ?string
{
    static $seq = 0;
    $reviewId = Ulid::generate();

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => $reviewId,
            'term_id' => $termId,
            'exercise_mode' => $mode,
            'response' => $response,
            'answered_at' => now()->toIso8601String(),
            'client_seq' => ++$seq,
            'latency_ms' => 4000,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $grade = DB::table('reviews')->where('id', $reviewId)->value('grade');

    return $grade !== null ? (string) $grade : null;
}

it('carries synonyms beside the accepted forms, never inside them', function () {
    [$user] = learner();
    $termId = seedWordFor($user, 'purpose', 'цель');
    seedSynonym($termId, 'goal');

    $key = app(TermAnswerKeyReader::class)->byIds([TermId::fromString($termId)])[$termId];

    // Apart, because they are accepted on different cards — and because a reader that merged them
    // would leave the grader nothing to tell the two apart with.
    expect($key->accepted)->toBe(['purpose'])
        ->and($key->synonyms)->toBe(['goal'])
        // The answer-key rule is untouched: still only target-language text the term owns.
        ->and($key->synonyms)->not->toContain('цель');
});

it('accepts a synonym typed from the translation, at full strength', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'purpose', 'цель');
    seedSynonym($termId, 'goal');

    // `typing` shows «цель» and asks for an English word. `goal` is one.
    expect(answerIn($this, $token, $termId, 'typing', 'goal'))->toBe('good');
});

it('does not accept a synonym on a card that asked for THIS word', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'purpose', 'цель');
    seedSynonym($termId, 'goal');

    // `listening` plays the word. Somebody who heard /ˈpɜːpəs/ and wrote `goal` did not do what the
    // card asked, and accepting it would make the trainer unable to test a word at all.
    expect(answerIn($this, $token, $termId, 'listening', 'goal'))->toBe('again')
        ->and(answerIn($this, $token, $termId, 'listening', 'purpose'))->toBe('good');
});

it('still accepts the term itself everywhere', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'purpose', 'цель');
    seedSynonym($termId, 'goal');

    // `good` and not `easy` only because the fixture's latency is ordinary — the point here is that
    // the term's own text is graded exactly as it was before synonyms existed.
    expect(answerIn($this, $token, $termId, 'typing', 'purpose'))->toBe('good');
});

it('every mode answers whether a synonym counts, and the sentence modes say no', function () {
    // Exhaustive by construction — `acceptsSynonyms()` is a `match` over every case, so a new mode
    // cannot be added without someone deciding this. What is pinned here is the DECISION, not the
    // exhaustiveness: the split is «prompt is the meaning» vs «prompt is this word».
    $accepting = [];
    foreach (ExerciseMode::cases() as $mode) {
        if ($mode === ExerciseMode::Intro) {
            continue;
        }
        if ($mode->acceptsSynonyms()) {
            $accepting[] = $mode->value;
        }
    }

    expect($accepting)->toBe(['multiple_choice', 'word_bank', 'typing', 'description_match', 'speaking']);
});
