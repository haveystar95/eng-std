<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

/**
 * WHAT A CARD EXPECTED, READ OFF ITS PAYLOAD (наряд ADM-1): the payload already carries the answer key a kind is checked
 * against — the text to say (`expected_text`, `expected`), the right option (`correct` → the option it names), the line of
 * one's own (`own_line`). This reads the first of them the payload has, in that order; nothing is computed.
 */
final class ExpectedAnswer
{
    /** @param array<string, mixed> $payload */
    public static function of(array $payload): ?string
    {
        foreach (['expected_text', 'expected'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_array($value)) {
                $words = array_filter($value, 'is_string');
                if ($words !== []) {
                    return implode(' ', $words);
                }
            }
        }
        $correct = $payload['correct'] ?? null;
        if (is_string($correct) && is_array($payload['options'] ?? null)) {
            foreach ($payload['options'] as $option) {
                if (is_array($option) && ($option['id'] ?? null) === $correct) {
                    return self::text($option) ?? $correct;
                }
            }

            return $correct;
        }
        if (is_array($payload['own_line'] ?? null)) {
            return self::text($payload['own_line']);
        }

        return null;
    }

    /** @param array<mixed> $node */
    private static function text(array $node): ?string
    {
        foreach (['text_target', 'text', 'target', 'native', 'text_native'] as $key) {
            if (is_string($node[$key] ?? null) && $node[$key] !== '') {
                return $node[$key];
            }
        }

        return null;
    }
}
