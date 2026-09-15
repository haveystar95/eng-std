<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\ValueObject\VoiceRole;

it('names the voice and its setting as two separate keys', function () {
    $voice = new LineVoice('elevenlabs', 'eleven_v3', '4tRn1lSkEn13EVTuqb0g', 0.5);

    // Голос — «кто говорит», вариант — «как». Оба стоят в уникальном ключе `plan_line_audios`, и разделены они потому,
    // что серверный файл нельзя переиграть на клиенте: смена стабильности обязана дать ДРУГОЙ файл, а не тот же.
    expect($voice->key())->toBe('elevenlabs:eleven_v3:4tRn1lSkEn13EVTuqb0g')
        ->and($voice->variant())->toBe('s50');
});

it('writes the stability as a short integer percent, never as a float', function () {
    // 0.3 * 100 в двоичной плавающей точке — это 30.000000000000004. Ключ, собранный конкатенацией, разошёлся бы
    // между двумя запусками одного и того же конфига.
    expect((new LineVoice('elevenlabs', 'eleven_v3', 'v', 0.3))->variant())->toBe('s30')
        ->and((new LineVoice('elevenlabs', 'eleven_v3', 'v', 1.0))->variant())->toBe('s100')
        ->and((new LineVoice('elevenlabs', 'eleven_v3', 'v', 0.0))->variant())->toBe('s0');
});

it('refuses a voice with a hole in it, and an impossible stability', function () {
    expect(fn () => new LineVoice('elevenlabs', '', 'v'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new LineVoice('elevenlabs', 'eleven_v3', 'v', 1.5))->toThrow(InvalidArgumentException::class);
});

// TTS-2: a man can be either person of a scene, and the partner who is a man must not sound like the learner who is one.
it('picks a voice by role and gender: a man partner and a man learner are two voices', function () {
    $row = static fn (string $id): array => ['provider' => 'elevenlabs', 'model' => 'eleven_v3', 'voice' => $id, 'stability' => 0.5];
    $catalog = new VoiceCatalog(['en' => [
        'partner' => ['female' => $row('woman'), 'male' => $row('man-partner')],
        'learner' => ['female' => $row('woman'), 'male' => $row('man-learner')],
    ]]);

    expect($catalog->forLanguage('EN', VoiceRole::Partner, VoiceGender::Male)?->voice)->toBe('man-partner')
        ->and($catalog->forLanguage('en', VoiceRole::Learner, VoiceGender::Male)?->voice)->toBe('man-learner')
        ->and($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female)?->voice)->toBe('woman')
        ->and($catalog->forLanguage('en', VoiceRole::Learner, VoiceGender::Female)?->voice)->toBe('woman');
});

it('answers «нет голоса» for a language the pack does not know, and for a broken row rather than throwing', function () {
    $catalog = new VoiceCatalog(['en' => ['partner' => ['female' => ['provider' => 'elevenlabs', 'voice' => 'v']]]]);

    // Это НЕ отказ: строки такого языка звучат системным синтезом телефона.
    expect($catalog->forLanguage('ro', VoiceRole::Partner, VoiceGender::Female))->toBeNull()
        ->and($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female))->toBeNull()
        ->and($catalog->forLanguage('en', VoiceRole::Learner, VoiceGender::Male))->toBeNull();
});
