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

// ПРАВИЛО: наряд SPEECH-2, Ч.3.2 — ключ ОБЯЗАТЕЛЕН и с ним покрытие остальных слов реплики.
// ЛОВИТ: возврат к «ключ есть — верно». Это ровно то, что было до наряда, и на телефоне 08.09 оно
// выглядело так: человек говорит длинную реплику, движок закрывается на первом узнанном ключевом
// слове и ставит «верно». Фразу никто не дослушал, а тренажёр, который учит говорить фразами,
// засчитал слово. Обе половины проверяются здесь, потому что ослабить можно любую.
it('needs the key AND the rest of the line, not the key alone', function () {
    [$user, $token] = learner();
    $termId = speakingLine(
        $user,
        "Yes, I'm looking for a place to rent for long-term living.",
        key: 'a place to rent',
        frame: "Yes, I'm looking for ___ for long-term living.",
    );

    // Most of the sentence, and not the thing the card teaches — the key is missing.
    expect(speakingVerdict($this, $token, $termId, 'Yes I am looking for a long-term living'))->toBe('again')
        // The key, and hardly anything else. Accepted before this наряд; the whole point of Ч.3.2
        // is that it is not an answer to a card that asks for a reply.
        ->and(speakingVerdict($this, $token, $termId, 'I want a place to rent'))->toBe('again')
        // The reply, said. Both halves are there, and this is what the card asked for.
        ->and(speakingVerdict(
            $this,
            $token,
            $termId,
            "Yes I'm looking for a place to rent for long-term living",
        ))->not->toBe('again');
});

// ПРАВИЛО: наряд SPEECH-2, Ч.3.2 — упрощённая форма это ДРУГОЙ СПОСОБ СКАЗАТЬ ВСЮ реплику, а не
// её кусок, и «остальных слов» у неё нет.
// ЛОВИТ: правило «ключ + остальное», применённое к перефразу. «my back hurts» не стоит в «It hurts
// in my lower back» сплошным куском, и требовать с него «ещё и остальные слова реплики» значит
// требовать сказать её дважды — то есть отменить канон GEN-1 Y4 боком.
it('takes a simpler form of the WHOLE reply as the whole reply', function () {
    [$user, $token] = learner();
    $termId = speakingLine($user, 'It hurts in my lower back.', key: 'in my lower back', frame: 'It hurts ___.');
    DB::table('terms')->where('id', $termId)->update(['speaking_keys' => json_encode(['my back hurts'])]);

    expect(speakingVerdict($this, $token, $termId, 'my back hurts'))->not->toBe('again')
        // …и это по-прежнему не «одно слово»: «back» — не способ сказать реплику.
        ->and(speakingVerdict($this, $token, $termId, 'back'))->toBe('again');
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

    [$user, $token, $planId] = startedPlan($this);
    // The spoken card is a word's stage C — two nights after it was met (DAY-FIX-2).
    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    walkDay($this, $token, $planId, 2, $seq);
    ageHistory($user->id, days: 1);

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
