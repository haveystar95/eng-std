<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Canonicalizes a user prompt so "иду в банк", "Иду в банк!" and "иду  в  банк" collapse
 * to one cache key. The normalized form is stored on the request for a future term-set
 * cache; it never changes what the model actually sees.
 */
final class PromptNormalizer
{
    public function normalize(string $prompt): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($prompt)) ?? '';
        $lower = mb_strtolower($collapsed);

        // Strip trailing whitespace/punctuation as CHARACTERS. `rtrim` with a multibyte char
        // (…) in its mask works byte-wise and can shear the last byte off a multibyte letter
        // (e.g. Cyrillic "р" = D1 80, and 0x80 is a byte of "…"), producing invalid UTF-8.
        return preg_replace('/[\s.!?…,;:]+$/u', '', $lower) ?? $lower;
    }
}
