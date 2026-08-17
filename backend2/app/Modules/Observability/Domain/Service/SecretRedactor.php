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
     * Credential-shaped VALUES, caught whatever they are keyed under. A key-only rule missed the
     * one that matters most: Gemini returns its minted ephemeral token as `name`
     * ("auth_tokens/…") — an innocuous key holding a live credential, which then sat in the log
     * table in clear. Anchored prefixes only, so ordinary text can't trip them.
     */
    private const SENSITIVE_VALUE_PREFIXES = [
        'auth_tokens/',   // Gemini Live ephemeral token resource name
        'sk-',            // OpenAI standing API key
        'ek_',            // OpenAI realtime ephemeral client secret
        'bearer ',        // any Authorization value that leaked into a body field
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
            if (is_string($value) && $this->isSensitiveValue($value)) {
                $out[$key] = self::REDACTED;

                continue;
            }
            $out[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $out;
    }

    private function isSensitiveValue(string $value): bool
    {
        $lower = strtolower($value);
        foreach (self::SENSITIVE_VALUE_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keys that merely CONTAIN a sensitive word but are plainly not credentials.
     *
     * `prompt_tokens`, `completion_tokens`, `total_tokens` — OpenAI's usage block. The substring
     * rule matched "token" in every one of them and replaced the counts with [REDACTED], which
     * quietly destroyed the only record of what a call cost. Plural `_tokens` is a count; a
     * credential is singular (`token`, `id_token`, `access_token`).
     */
    private const COUNT_KEY_PATTERN = '/_tokens(_details)?$/';

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);

        if (preg_match(self::COUNT_KEY_PATTERN, $lower) === 1) {
            return false;
        }

        foreach (self::SENSITIVE as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
