<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * ЧЕМ ГОВОРИТ РЕПЛИКА — вендор, модель, голос и темп, одним значением (наряд TTS-1).
 *
 * Живёт в ядре, потому что его читают ОБА берега: Generation покупает у вендора звук этим голосом,
 * Vocabulary хранит файл под его ключом, а Learning спрашивает «есть ли у этой реплики файл ЭТОГО
 * голоса». Три модуля, одно понятие — значит Shared, иначе у каждого будет своя строка формата
 * «openai/coral» и они разойдутся ровно на том дне, когда голос сменят.
 *
 * ## Ключ и вариант — две разные вещи, и обе в уникальном индексе `plan_line_audios`
 *
 * - {@see key()} — «кто говорит»: `openai:gpt-4o-mini-tts:coral`.
 * - {@see variant()} — «как говорит»: темп, `p90` для speed = 0.9.
 *
 * Темп отдельно от голоса, потому что серверный файл ускорить на клиенте нельзя (канон §7: темп
 * реплик — своя ручка, и он закладывается ПРИ ГЕНЕРАЦИИ). Смена ручки обязана дать другой файл, а
 * не переиграть тот же быстрее, — иначе ручка врёт.
 */
final readonly class LineVoice
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $voice,
        /**
         * Темп подачи, где 1.0 — обычная речь вендора. Реплики звучат МЕДЛЕННЕЕ слов (канон §7),
         * поэтому дефолт пакета ниже единицы; здесь только проверка границ, решение — в конфиге.
         */
        public float $speed = 1.0,
    ) {
        if (trim($provider) === '' || trim($model) === '' || trim($voice) === '') {
            throw new InvalidArgumentException('a voice needs a provider, a model and a voice name');
        }
        if ($speed < 0.25 || $speed > 2.0) {
            throw new InvalidArgumentException("speech speed out of range: {$speed}");
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
            speed: (float) ($row['speed'] ?? 1.0),
        );
    }

    /** «Кто говорит», as stored in `plan_line_audios.voice`. */
    public function key(): string
    {
        return "{$this->provider}:{$this->model}:{$this->voice}";
    }

    /**
     * «Как говорит», as stored in `plan_line_audios.variant`. Integer percent so the value is a stable
     * short string: 0.9 → `p90`, and never `p90.00000000001`.
     */
    public function variant(): string
    {
        return 'p' . (string) (int) round($this->speed * 100);
    }
}
