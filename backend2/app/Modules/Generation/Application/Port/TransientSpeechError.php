<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use RuntimeException;

/**
 * Сбой озвучки, который стоит повторить: лимит вендора, 5xx, сеть. Часть контракта {@see SpeechSynthesizerPort} —
 * адаптер сначала повторяет сам (коротко, с паузой), потом бросает, и джоба ждёт.
 *
 * Отдельный тип, а не флаг в сообщении, потому что от него зависят ДЕНЬГИ: повтор невозвратной ошибки — это второй
 * запрос за тот же отказ. Отказ прочитать текст невозвратен и бросается обычным исключением, отказ аккаунта —
 * {@see SpeechAccountError}.
 *
 * `retryAfterSeconds` — сколько вендор попросил подождать (`Retry-After`), если попросил: 429 ElevenLabs при превышении
 * одновременности живьём приходит без этого заголовка (15.09), и тогда ждёт джоба сама.
 */
final class TransientSpeechError extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function rateLimited(string $provider, ?int $retryAfterSeconds, string $detail = ''): self
    {
        return new self(trim("{$provider} speech rate limit hit {$detail}"), $retryAfterSeconds);
    }

    public static function upstream(string $provider, int $status): self
    {
        return new self("{$provider} speech upstream error: {$status}");
    }

    public static function network(string $provider, string $detail): self
    {
        return new self("{$provider} speech network error: {$detail}");
    }
}
