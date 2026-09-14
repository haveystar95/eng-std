<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechNotCut;
use App\Modules\Generation\Infrastructure\Adapter\PcmTurnCutter;

/**
 * ONE SOUND → THE LINES OF A SCRIPT (DAY-UI-3). The sound here is built, not recorded: a line is a
 * run of «syllables» (a 300 Hz tone, 90 ms each, 40 ms gaps between them), a sentence break inside a
 * line is a 380 ms pause, a turn is followed by a 520 ms pause. Every line's true start is known.
 */

const CUT_RATE = 24000;

/** @return array{0: string, 1: list<int>} PCM and where every line really starts, ms */
function cutSound(array $lines, int $turnPauseMs = 520, int $sentencePauseMs = 380): array
{
    $pcm = '';
    $starts = [];
    $ms = static fn (string $bytes): int => intdiv(strlen($bytes) * 1000, CUT_RATE * 2);
    $silence = static fn (int $millis): string => str_repeat("\x00\x00", intdiv(CUT_RATE * $millis, 1000));
    $syllable = static function (): string {
        $out = '';
        $samples = intdiv(CUT_RATE * 90, 1000);
        for ($i = 0; $i < $samples; $i++) {
            $out .= pack('v', (int) round(9000 * sin(2 * M_PI * 300 * $i / CUT_RATE)) & 0xFFFF);
        }

        return $out;
    };
    foreach ($lines as $index => $line) {
        if ($index > 0) {
            $pcm .= $silence($turnPauseMs);
        }
        $starts[] = $ms($pcm);
        foreach (explode('. ', $line) as $s => $sentence) {
            if ($s > 0) {
                $pcm .= $silence($sentencePauseMs);
            }
            $syllables = max(2, (int) ceil(mb_strlen($sentence) / 3));
            for ($k = 0; $k < $syllables; $k++) {
                $pcm .= $syllable().$silence(40);
            }
        }
    }

    return [$pcm, $starts];
}

it('cuts a dialogue at its turns, not at the sentence pauses inside a line — catches «the longest pauses» cut', function () {
    $lines = [
        'Hello, please have a seat. What brings you in today?',
        'My lower back hurts.',
        'I see. How long have you had this pain?',
        'About a week.',
        'I will prescribe a painkiller. Take it after meals.',
        'Thank you, doctor.',
    ];
    [$pcm, $starts] = cutSound($lines);

    $spans = (new PcmTurnCutter())->spans($pcm, CUT_RATE, $lines);

    expect($spans)->toHaveCount(6);
    foreach ($spans as $i => [$from]) {
        expect(abs($from - $starts[$i]))->toBeLessThanOrEqual(120);
    }
    expect((new PcmTurnCutter())->cut($pcm, CUT_RATE, $lines))->toHaveCount(6);
});

it('cuts a batch of words read one by one', function () {
    $words = ['appointment', 'lower back', 'prescription', 'side effect', 'symptoms', 'painkiller', 'referral', 'dizzy'];
    [$pcm, $starts] = cutSound($words, turnPauseMs: 900);

    $spans = (new PcmTurnCutter())->spans($pcm, CUT_RATE, $words);

    foreach ($spans as $i => [$from]) {
        expect(abs($from - $starts[$i]))->toBeLessThanOrEqual(120);
    }
});

// A sound with fewer pauses than seams, or pieces nothing like their lines, must not be cut: a wrongly cut
// sound plays another line's words under this line's text.
it('refuses a sound that has fewer pauses than lines, and a cut whose pieces are nothing like their lines', function () {
    [$pcm] = cutSound(['one long line with many words in it and no pause']);

    expect(fn () => (new PcmTurnCutter())->spans($pcm, CUT_RATE, ['one long line', 'with many words', 'in it']))
        ->toThrow(SpeechNotCut::class);

    [$two] = cutSound(['A very long first line that goes on and on and on for a while', 'Yes.']);
    expect(fn () => (new PcmTurnCutter())->spans($two, CUT_RATE, ['Yes.', 'A very long first line that goes on and on and on for a while']))
        ->toThrow(SpeechNotCut::class);
});
