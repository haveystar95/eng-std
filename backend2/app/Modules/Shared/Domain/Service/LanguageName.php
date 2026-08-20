<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * The NAME of a language, for prompts that are written around it.
 *
 * A prompt that says "write in ru" asks a model to interpret an ISO code, which is not what it is
 * good at; every prompt in this app therefore carries `{{source_lang}}` / `{{target_lang}}` as
 * "Russian" and "English". The mapping lives here, once, because the rendered prompt's digest is
 * part of a content row's provenance: two callers with two private copies of this table would render
 * two different prompts for the same language pair and record them under the same version.
 *
 * An unknown code falls through as itself rather than being guessed at — a prompt naming `sw` is a
 * visible defect; a prompt naming "Swedish" for `sw` is an invisible one.
 */
final class LanguageName
{
    /** @var array<string, string> */
    private const NAMES = [
        'en' => 'English',
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'es' => 'Spanish',
        'de' => 'German',
        'fr' => 'French',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
    ];

    public static function of(string $code): string
    {
        return self::NAMES[$code] ?? $code;
    }
}
