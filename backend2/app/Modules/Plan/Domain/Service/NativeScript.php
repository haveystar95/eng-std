<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * What a READING may be written in. A pronunciation guide is in the learner's own alphabet;
 * for a Cyrillic native language that is Cyrillic letters, digits, punctuation, whitespace and the
 * stress mark U+0301 — nothing else. For a native language whose script is not tabled here the
 * check is switched off rather than guessed.
 */
final class NativeScript
{
    private const CYRILLIC = ['ru', 'uk', 'be', 'bg', 'sr', 'mk', 'kk'];

    public static function isChecked(string $nativeLang): bool
    {
        return in_array(strtolower($nativeLang), self::CYRILLIC, true);
    }

    public static function isValidReading(string $nativeLang, string $reading): bool
    {
        if (! self::isChecked($nativeLang)) {
            return true;
        }

        return preg_match('/^[\p{Cyrillic}\p{N}\p{P}\s\x{0301}]*$/u', $reading) === 1;
    }
}
