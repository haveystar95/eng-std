<?php

declare(strict_types=1);

namespace App\Modules\Observability\Domain\Service;

/**
 * Replaces the values of secret-bearing keys with a placeholder before a body or
 * header set is persisted. Pure and recursive — the one invariant of this module is
 * that credentials never reach the log table (Google id_token, Sanctum/bearer tokens,
 * the OpenAI API key, passwords, cookies).
 *
 * Matching is a case-insensitive substring on the key, so it catches variants like
 * `id_token`, `access_token`, `x-api-key`, `client_secret` without an exhaustive list.
 */
final class SecretRedactor
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE = [
        'authorization',
        'password',
        'token',
        'secret',
        'api_key',
        'api-key',
        'apikey',
        'cookie',
    ];

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public function redact(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $out[$key] = self::REDACTED;

                continue;
            }
            $out[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $out;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
