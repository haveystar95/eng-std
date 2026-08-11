<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Attach a pinned example to a seeded term. */
function withExample(string $termId, string $sentence, ?string $translation = 'У меня бронь на сегодня.'): void
{
    DB::table('term_examples')->insert([
        'id' => Ulid::generate(),
        'term_id' => $termId,
        'sentence' => $sentence,
        'sentence_translation' => $translation,
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Upload one scramble answer and return the grade the server derived FOR THAT answer — looked up
 * by the review's own id, so a test that answers twice reads the row it just wrote.
 */
function scrambleAnswer(object $ctx, string $token, string $termId, string $response): ?string
{
    static $seq = 0;
    $reviewId = Ulid::generate();

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => $reviewId,
            'term_id' => $termId,
            'exercise_mode' => 'scramble',
            'response' => $response,
            'answered_at' => now()->toIso8601String(),
            'client_seq' => ++$seq,
            'latency_ms' => 6000,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $grade = DB::table('reviews')->where('id', $reviewId)->value('grade');

    return $grade !== null ? (string) $grade : null;
}

it('carries the pinned example in the answer key, without letting a translation in', function () {
    [$user] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    withExample($termId, 'I have a reservation for tonight.');

    $key = app(TermAnswerKeyReader::class)->byIds([TermId::fromString($termId)])[$termId];

    expect($key->example)->toBe('I have a reservation for tonight.')
        ->and($key->accepted)->toBe(['reservation'])   // the term's own forms, unchanged
        ->and($key->accepted)->not->toContain('бронь'); // the prompt side is still never a key
});

it('grades an assembled sentence against the example, not against the term', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    withExample($termId, 'I have a reservation for tonight.');

    // What the client uploads: the chips joined by single spaces, exactly like word_bank.
    expect(scrambleAnswer($this, $token, $termId, 'I have a reservation for tonight'))->toBe('good');
});

it('accepts the sentence however the learner punctuated and capitalised it', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    withExample($termId, 'I have a reservation for tonight.');

    expect(scrambleAnswer($this, $token, $termId, 'i have a reservation for tonight.'))->toBe('good');
});

it('marks a wrong word order wrong', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    withExample($termId, 'I have a reservation for tonight.');

    expect(scrambleAnswer($this, $token, $termId, 'I have for tonight a reservation'))->toBe('again');
});

it('never awards easy — every word was on the screen', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    withExample($termId, 'I have a reservation for tonight.');

    // A fast, clean, hint-free answer: `easy` for typing, capped at `good` for assembly.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'scramble',
            'response' => 'I have a reservation for tonight',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1, 'latency_ms' => 300,
        ]]])
        ->assertOk();

    expect(DB::table('reviews')->where('term_id', $termId)->value('grade'))->toBe('good');
});

it('falls back to the term forms when the example vanished under the session', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    withExample($termId, 'I have a reservation for tonight.');
    // "New example" replaces the row mid-session; the sentence the card showed is simply gone.
    DB::table('term_examples')->where('term_id', $termId)->delete();

    // The assembled sentence no longer matches anything → an honest miss, not a crash.
    expect(scrambleAnswer($this, $token, $termId, 'I have a reservation for tonight'))->toBe('again');
    // …and the term's own text still grades as it always did.
    expect(scrambleAnswer($this, $token, $termId, 'reservation'))->toBe('good');
});
