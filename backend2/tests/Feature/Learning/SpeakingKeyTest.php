<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A SPOKEN LINE IS JUDGED ON ITS KEY — PLAN-FIX-4 п. 1.4.
 *
 * The card is «say the reply that uses this word»; the frame around the word is scaffolding the
 * learner reads off the screen. Graded by coverage of all fifteen words it told the owner «Не то»
 * three sittings running, with seven words underlined that the card had never asked for — under a
 * caption saying it was checking whether the WORD was remembered.
 *
 * Both halves are one string: the key the client is sent and the key the server grades against come
 * off the same column, so the phone cannot underline one thing while the log records another.
 */
function speakingLine(object $user, string $text, string $key, string $frame): string
{
    $termId = seedWordFor($user, $text, 'Да, я ищу жильё для долгого проживания.', enroll: true);

    DB::table('terms')->where('id', $termId)->update([
        'kind' => 'line',
        'type' => 'phrase',
        'speaker' => 'learner',
        'frame' => $frame,
        'filler' => $key,
        'speaking_key' => $key,
    ]);

    return $termId;
}

/** One spoken answer, graded by the server; returns the grade it wrote. */
function speakingVerdict(object $ctx, string $token, string $termId, string $response): string
{
    static $seq = 0;
    $reviewId = Ulid::generate();

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => $reviewId,
            'term_id' => $termId,
            'exercise_mode' => 'speaking',
            'response' => $response,
            'answered_at' => now()->toIso8601String(),
            'client_seq' => ++$seq,
            'latency_ms' => 6000,
            'ladder_step' => 3,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    return (string) DB::table('reviews')->where('id', $reviewId)->value('grade');
}

it('marks a reading that misses the key wrong, and one that has it right', function () {
    [$user, $token] = learner();
    $termId = speakingLine(
        $user,
        "Yes, I'm looking for a place to rent for long-term living.",
        key: 'a place to rent',
        frame: "Yes, I'm looking for ___ for long-term living.",
    );

    // Most of the sentence, and not the thing the card teaches.
    expect(speakingVerdict($this, $token, $termId, 'Yes I am looking for a long-term living'))->toBe('again')
        // The key, and hardly anything else — which is the card answered.
        ->and(speakingVerdict($this, $token, $termId, 'I want a place to rent'))->not->toBe('again');
});

it('keeps asking for the whole line when the day left no key', function () {
    [$user, $token] = learner();
    $termId = speakingLine($user, 'Sorry, could you repeat that?', key: 'x', frame: 'x');
    DB::table('terms')->where('id', $termId)->update(['speaking_key' => null, 'filler' => null, 'frame' => null]);

    expect(speakingVerdict($this, $token, $termId, 'sorry could you repeat that'))->not->toBe('again')
        ->and(speakingVerdict($this, $token, $termId, 'sorry'))->toBe('again');
});

it('sends the client the same key it grades by', function () {
    fakePlanModel();
    // `speaking` ships dark; without the owner's switch the sitting has no spoken card in it and
    // this test would pass by measuring nothing.
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);

    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    $keys = DB::table('terms')->pluck('speaking_key', 'id')->all();
    $seen = 0;
    foreach ($session['tasks'] as $task) {
        $card = $task['card'];
        if ($card['exercise_mode'] !== 'speaking') {
            // Every other trainer carries nothing — the field is the spoken card's alone.
            expect($card['speaking_key'])->toBeNull();

            continue;
        }
        expect($card)->toHaveKey('speaking_key')
            ->and($card['speaking_key'])->toBe($keys[$card['term_id']] ?? null);
        $seen++;
    }

    expect($seen)->toBeGreaterThan(0);
});
