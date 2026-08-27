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

/** Give the term the pinned example dictation reads out. */
function dictationExample(string $termId, string $sentence): void
{
    seedExample([
        'term_id' => $termId,
        'sentence' => $sentence,
        'translation' => 'У меня бронь на сегодня.',
        'source' => 'ai',
    ]);
}

/** Switch dictation on for this user only — it ships off, so every test has to opt in. */
function enableDictationFor(string $userId): void
{
    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($userId),
        new EnabledModes([ExerciseMode::Dictation]),
    );
}

/** Upload one dictation answer and return the grade the server derived FOR IT. */
function dictationAnswer(object $ctx, string $token, string $termId, string $response, int $latencyMs = 6000): ?string
{
    static $seq = 0;
    $reviewId = Ulid::generate();

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => $reviewId,
            'term_id' => $termId,
            'exercise_mode' => 'dictation',
            'response' => $response,
            'answered_at' => now()->toIso8601String(),
            'client_seq' => ++$seq,
            'latency_ms' => $latencyMs,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    $grade = DB::table('reviews')->where('id', $reviewId)->value('grade');

    return $grade !== null ? (string) $grade : null;
}

it('ships switched off — a fresh install never deals it', function () {
    // The release rule, asserted where it would actually break: the default set the migration
    // seeded, which is what every user without an override trains with.
    expect(config('learning.enabled_modes'))->not->toContain('dictation');

    // One row per (scope, mode) since the admission matrix moved into this table: the trainer HAS a
    // row — it has to, the matrix lives there — and that row is switched off.
    $row = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'dictation')->first();
    expect($row)->not->toBeNull()->and((bool) $row->enabled)->toBeFalse();
});

it('ships the intro switched off too — a new trainer is a data change, not a deploy', function () {
    // Same release rule, for the rung-0 trainer this ladder introduces. Until someone turns it on,
    // a never-seen pair simply starts at recognition, which is where it started before the ladder.
    expect(config('learning.enabled_modes'))->not->toContain('intro');

    $row = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'intro')->first();
    expect($row)->not->toBeNull()
        ->and((bool) $row->enabled)->toBeFalse()
        ->and((string) $row->min_acquisition)->toBe('new');
});

it('builds a card with no written prompt — the audio IS the task', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');
    // Off the recognition rungs first: dictation is withheld from a pair with no rung of its own —
    // a word may be on screen for the first time, and «напиши, что услышал» is not a first meeting.
    // What is under test is the CARD, so the pair has to be one the trainer is opened to at all.
    answerTimes($this, $token, $termId, 'reservation', times: 3);
    enableDictationFor($user->id);

    $card = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 5, 'practice' => true])
        ->assertOk()
        ->json('data.cards.0');

    expect($card['exercise_mode'])->toBe('dictation')
        // A translation on screen would make it "translate this", which is an easier exercise.
        ->and($card['prompt'])->toBeNull()
        ->and($card['answer'])->toBe('I have a reservation for tonight.')
        ->and($card['options'])->toBeNull()
        ->and($card['chips'])->toBeNull();
});

it('grades what was typed against the sentence, not against the term', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');

    expect(dictationAnswer($this, $token, $termId, 'I have a reservation for tonight'))->toBe('good');
});

it('forgives the punctuation and capitals nobody types by ear', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');

    expect(dictationAnswer($this, $token, $termId, 'i have a reservation for tonight.'))->toBe('good');
});

it('marks a mis-heard sentence wrong', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');

    expect(dictationAnswer($this, $token, $termId, 'I have a registration for tonight'))->toBe('again');
});

it('records «Не помню» as a miss, not as a lost answer (F21)', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');

    // The client sends an empty response for «Не помню»; it must be stored and graded, never 422'd.
    expect(dictationAnswer($this, $token, $termId, ''))->toBe('again');
    expect(DB::table('reviews')->where('term_id', $termId)->where('exercise_mode', 'dictation')->count())->toBe(1);
});

it('can reach easy, unlike the assembling modes', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');

    // Fast, clean, no hint — production from hearing alone earns the full grade.
    expect(dictationAnswer($this, $token, $termId, 'I have a reservation for tonight', latencyMs: 300))->toBe('easy');
});

it('measures speed as a phrase — a sentence is not held to a single word budget', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'reservation', 'бронь');
    dictationExample($termId, 'I have a reservation for tonight.');

    // 10 s would be "slow" for one word (8 s bound) and is normal for a whole sentence (15 s).
    expect(dictationAnswer($this, $token, $termId, 'I have a reservation for tonight', latencyMs: 10000))->toBe('good');
});
