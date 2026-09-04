<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\SpeechCost;

it('prices a per-character model by its characters', function () {
    // tts-1: $15 за 1M символов. Реплика дня 1 qa-плана — 143 символа полки hear.
    expect(SpeechCost::estimate('tts-1', 143, 9500))->toBe('0.002145');
});

it('prices a per-token model by the SECONDS it produced, not by the characters', function () {
    // gpt-4o-mini-tts: $12 за 1M звуковых токенов, 20 токенов в секунде → $0.00024 в секунду.
    // 5 секунд = 0.0012, плюс текстовый вход 143/4 токена по $0.60/1M = 0.0000215.
    expect(SpeechCost::estimate('gpt-4o-mini-tts', 143, 5000))->toBe('0.001221');
});

it('makes a slower reading of the same line cost MORE on a per-token model', function () {
    $fast = (float) SpeechCost::estimate('gpt-4o-mini-tts', 100, 4000);
    $slow = (float) SpeechCost::estimate('gpt-4o-mini-tts', 100, 6000);

    // Ручка темпа реплик (канон §7) — это ручка цены, и это должно быть видно, а не всплыть в счёте.
    expect($slow)->toBeGreaterThan($fast);
});

it('leaves a model it has no rate for unpriced, never free', function () {
    // То же правило, что и у ModelCast: неизвестная ставка как ноль отдала бы самый дешёвый столбец
    // тому вендору, чью цену просто не завели.
    expect(SpeechCost::estimate('some-unknown-tts', 100, 3000))->toBeNull()
        ->and(SpeechCost::estimate('gpt-4o-mini-tts', 100, null))->toBeNull();
});

it('brings both billing shapes to one measure so a bake-off compares like with like', function () {
    // 13.8 знака в секунду — темп, замеренный на живых образцах Gemini в Ч.0.3.
    expect(SpeechCost::perMillionCharacters('tts-1', 13.8))->toBe(15.0)
        ->and(SpeechCost::perMillionCharacters('gemini-2.5-flash-preview-tts', 13.8))
        ->toBeGreaterThan(17.0)
        ->toBeLessThan(19.0);
});

it('reads an mp3 duration off its weight at OpenAI’s constant bitrate', function () {
    // 128 кбит/с = 16 000 байт/с — измерено по двадцати живым файлам наряда.
    expect(SpeechCost::mp3DurationMs(49536))->toBe(3096);
});
