<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\LineVoice;

it('names the voice and the pace as two separate keys', function () {
    $voice = new LineVoice('openai', 'gpt-4o-mini-tts', 'coral', 0.9);

    // Голос — «кто говорит», вариант — «как». Оба стоят в уникальном ключе `plan_line_audios`, и
    // разделены они потому, что серверный файл нельзя ускорить на клиенте: смена темпа обязана
    // дать ДРУГОЙ файл, а не переиграть тот же быстрее.
    expect($voice->key())->toBe('openai:gpt-4o-mini-tts:coral')
        ->and($voice->variant())->toBe('p90');
});

it('writes the pace as a short integer percent, never as a float', function () {
    // 0.9 * 100 в двоичной плавающей точке — это 90.00000000000001. Ключ, собранный конкатенацией,
    // разошёлся бы между двумя запусками одного и того же конфига.
    expect((new LineVoice('openai', 'tts-1', 'nova', 0.9))->variant())->toBe('p90')
        ->and((new LineVoice('openai', 'tts-1', 'nova', 1.0))->variant())->toBe('p100')
        ->and((new LineVoice('openai', 'tts-1', 'nova', 0.75))->variant())->toBe('p75');
});

it('refuses a voice with a hole in it, and an impossible pace', function () {
    expect(fn () => new LineVoice('openai', '', 'coral'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new LineVoice('openai', 'tts-1', 'nova', 4.0))->toThrow(InvalidArgumentException::class);
});

it('answers «нет голоса» for a language the pack does not know', function () {
    $catalog = new VoiceCatalog(['en' => ['provider' => 'openai', 'model' => 'tts-1', 'voice' => 'nova']]);

    // Это НЕ отказ: реплики такого языка звучат системным синтезом телефона, как звучали до наряда.
    expect($catalog->forLanguage('ro'))->toBeNull()
        ->and($catalog->forLanguage('EN')?->key())->toBe('openai:tts-1:nova');
});

it('answers «нет голоса» for a broken row rather than throwing on a день, который уже готов', function () {
    $catalog = new VoiceCatalog(['en' => ['provider' => 'openai', 'voice' => 'nova']]);

    expect($catalog->forLanguage('en'))->toBeNull();
});
