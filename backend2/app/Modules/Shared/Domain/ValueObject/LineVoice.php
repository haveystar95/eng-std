<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * ЧЕМ ГОВОРИТ РЕПЛИКА — вендор, модель, голос и его настройка, одним значением (наряды TTS-1, TTS-2).
 *
 * Живёт в ядре, потому что его читают ОБА берега: Generation покупает у вендора звук этим голосом, Plan
 * хранит файл под его ключом и ищет «есть ли у этой строки файл ЭТОГО голоса». Два модуля, одно понятие —
 * иначе у каждого будет своя строка формата «vendor/voice», и они разойдутся ровно в тот день, когда голос
 * сменят.
 *
 * ## Ключ и вариант — две разные вещи, и обе в уникальном индексе `plan_line_audios`
 *
 * - {@see key()} — «кто говорит»: `elevenlabs:eleven_v3:<voice id>`.
 * - {@see variant()} — «как говорит»: стабильность голоса, `s50` для 0.5 (пресет Natural у v3).
 *
 * Настройка отдельно от голоса, потому что серверный файл переиграть на клиенте нельзя: смена ручки обязана
 * дать ДРУГОЙ файл по другому адресу, а не подменить тот, что телефон уже скачал (DECISIONS п. 248).
 */
final readonly class LineVoice
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $voice,
        /**
         * Стабильность голоса, 0…1. У Eleven v3 это три пресета: Creative (0.0), Natural (0.5) и Robust (1.0);
         * решение — в конфиге пакета, здесь только границы.
         */
        public float $stability = 0.5,
    ) {
        if (trim($provider) === '' || trim($model) === '' || trim($voice) === '') {
            throw new InvalidArgumentException('a voice needs a provider, a model and a voice id');
        }
        if ($stability < 0.0 || $stability > 1.0) {
            throw new InvalidArgumentException("voice stability out of range: {$stability}");
        }
    }

    /**
     * @param  array<string, mixed>  $row  a language pack's voice entry
     */
    public static function fromArray(array $row): self
    {
        return new self(
            provider: (string) ($row['provider'] ?? ''),
            model: (string) ($row['model'] ?? ''),
            voice: (string) ($row['voice'] ?? ''),
            stability: (float) ($row['stability'] ?? 0.5),
        );
    }

    /** «Кто говорит», the first half of `plan_line_audios.voice_key`. */
    public function key(): string
    {
        return "{$this->provider}:{$this->model}:{$this->voice}";
    }

    /**
     * «Как говорит», the second half of `plan_line_audios.voice_key`. Integer percent so the value is a stable
     * short string: 0.5 → `s50`, and never `s50.00000000001`.
     */
    public function variant(): string
    {
        return 's'.(string) (int) round($this->stability * 100);
    }
}
