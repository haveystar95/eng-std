<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use RuntimeException;

/**
 * Сбой озвучки, который стоит повторить: лимит вендора, 5xx, сеть. Часть контракта
 * {@see SpeechSynthesizerPort} — адаптер бросает, джоба ждёт и повторяет.
 *
 * Отдельный тип, а не флаг в сообщении, потому что от него зависят ДЕНЬГИ: повтор невозвратной
 * ошибки — это второй счёт за тот же отказ. Ответ «не могу озвучить этот текст» невозвратен и
 * бросается обычным исключением.
 *
 * Лимит бывает ДВУХ окон (DAY-UI-3; Gemini TTS: 10 запросов в минуту и 100 в сутки): поминутный
 * проходит за минуту, суточный — к полуночи вендора. `perDay` и `retryAfterSeconds` — ровно то, что
 * вендор сказал сам (`QuotaFailure.quotaId`, `RetryInfo.retryDelay`): джоба ждёт до следующего окна,
 * а не долбит закрытую дверь раз в минуту.
 */
final class TransientSpeechError extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        public readonly bool $perDay = false,
    ) {
        parent::__construct($message);
    }

    public static function rateLimited(string $provider, ?int $retryAfterSeconds, bool $perDay = false): self
    {
        return new self(
            "{$provider} speech rate limit hit".($perDay ? ' (daily quota)' : ''),
            $retryAfterSeconds,
            $perDay,
        );
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
