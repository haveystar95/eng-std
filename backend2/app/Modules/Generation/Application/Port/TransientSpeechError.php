<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use RuntimeException;

/**
 * Сбой озвучки, который стоит повторить: лимит вендора, 5xx, сеть. Часть контракта
 * {@see SpeechSynthesizerPort} — адаптер бросает, джоба ретраит с backoff.
 *
 * Отдельный тип, а не флаг в сообщении, потому что от него зависит ДЕНЬГИ: повтор невозвратной
 * ошибки — это второй счёт за тот же отказ. Ответ «не могу озвучить этот текст» невозвратен и
 * бросается обычным исключением.
 */
final class TransientSpeechError extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function rateLimited(string $provider, ?int $retryAfterSeconds): self
    {
        return new self("{$provider} speech rate limit hit", $retryAfterSeconds);
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
