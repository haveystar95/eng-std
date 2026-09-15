<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\SpeechCost;

// TTS-2: the vendor's tariff is per thousand characters of text, by model — v3 Conversational $0.05, v3 $0.10.
it('names each model’s rate per thousand characters: v3 Conversational — $0.05, v3 — $0.10, an unknown model — none', function () {
    expect(SpeechCost::perThousandCharacters('eleven_v3_conversational'))->toBe(0.05)
        ->and(SpeechCost::perThousandCharacters('eleven_v3'))->toBe(0.1)
        ->and(SpeechCost::perThousandCharacters('some-unknown-model'))->toBeNull()
        ->and(SpeechCost::ofCharacters('eleven_v3_conversational', 31))->toBe('0.001550')
        ->and(SpeechCost::ofCharacters('some-unknown-model', 31))->toBeNull();
});

// Live 15.09 on Starter: 31 characters on v3 Conversational — `character-cost: 8`; $6 buys 30 000 credits.
it('prices the credits the vendor debited at the account’s credit price — and it meets the model’s rate but for the vendor’s rounding up', function () {
    expect(SpeechCost::ofCredits(8, 0.20))->toBe('0.001600')
        ->and(SpeechCost::ofCredits(0, 0.20))->toBe('0.000000')
        ->and(SpeechCost::charactersOf('  Hello, how are you doing today? '))->toBe(31);
});

it('reads how long an mp3 of the bought format sounds from its weight', function () {
    expect(SpeechCost::mp3DurationMs(16000))->toBe(1000)
        ->and(SpeechCost::mp3DurationMs(25539))->toBe(1596);
});
