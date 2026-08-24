<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

use Normalizer;

/**
 * Unicode hygiene, in two forms that must never be confused.
 *
 * The problem is that the same word has more than one byte sequence and no user knows which one
 * they typed. «știi» can arrive as `s` + U+0326 (combining comma below), as U+0219 (the precomposed
 * Romanian letter), or as U+015F — `ş`, the CEDILLA letter, which is what most keyboards and most
 * model outputs actually produce because the two look nearly identical at text size. Three
 * spellings, one word, and a byte comparison calls two of them wrong.
 *
 * So there are two forms, and which one a caller wants is decided by what it is about to do:
 *
 * - {@see canonical()} is for STORING. It is lossless as far as the reader is concerned: NFC
 *   composition, plus the Romanian comma-below letters written the way the Romanian standard says
 *   they are written. Content comes out of it looking like itself.
 * - {@see fold()} is for COMPARING. It is deliberately lossy — `ß` becomes `ss`, `œ` becomes `oe` —
 *   because a learner who typed «strasse» for «Straße» knows the word, and a grader that says
 *   otherwise is testing their keyboard. Nothing folded is ever written to the database.
 *
 * This is DECISIONS п. 87: «Юникод-нормализация — безусловно для всех языков: это корректность,
 * а не строгость.» Unconditional, and that word is load-bearing — a per-language rule would need a
 * language to be known at every call site, and the call sites that matter (a grader comparing two
 * strings, a writer storing one) are exactly where it is most often not.
 *
 * ## The one place the unconditional rule costs something
 *
 * `ş`/`ţ` with a cedilla are correct Turkish letters, and {@see canonical()} rewrites them into the
 * Romanian comma-below ones. That is only harmless while no Turkish content exists — `tr` is in
 * {@see LanguageCatalog} but is neither a taught language nor a support language in v1 (DECISIONS
 * пп. 83, 85), so today nothing can be damaged. The day Turkish is taught, this method needs the
 * language it is normalising FOR, and the mapping below becomes conditional on it. Written down
 * here rather than discovered then.
 */
final class TextNormalizer
{
    /**
     * Cedilla → comma-below, for the two letters where the distinction is a real orthographic rule
     * rather than a font choice. Applied AFTER NFC, so a decomposed `s` + combining mark has already
     * been composed into one of these.
     */
    private const CEDILLA_TO_COMMA_BELOW = [
        "\u{015F}" => "\u{0219}",   // ş → ș
        "\u{015E}" => "\u{0218}",   // Ş → Ș
        "\u{0163}" => "\u{021B}",   // ţ → ț
        "\u{0162}" => "\u{021A}",   // Ţ → Ț
    ];

    /**
     * Equivalences that exist only for COMPARISON: two spellings of one word that a learner may
     * legitimately produce either of.
     *
     * `ß`/`ss` is German's own rule (Swiss German writes `ss` throughout), and `œ`/`oe` is French's
     * — «cœur» and «coeur» are the same word, and only one of them is on a keyboard.
     */
    private const FOLD_EQUIVALENCES = [
        "\u{00DF}" => 'ss',         // ß → ss
        "\u{1E9E}" => 'SS',         // ẞ → SS
        "\u{0153}" => 'oe',         // œ → oe
        "\u{0152}" => 'OE',         // Œ → OE
    ];

    /**
     * The form content is STORED in: NFC, with Romanian's comma-below letters written as such.
     *
     * Idempotent — running it over already-canonical text changes nothing, which is what lets the
     * cleanup migration and the writers use the same function without arguing about who ran first.
     */
    public function canonical(string $value): string
    {
        $composed = Normalizer::normalize($value, Normalizer::FORM_C);
        if (! is_string($composed)) {
            // Malformed UTF-8: nothing sensible to compose, and silently blanking the string would
            // be worse than storing exactly what arrived. The write itself is what will complain.
            $composed = $value;
        }

        return strtr($composed, self::CEDILLA_TO_COMMA_BELOW);
    }

    /**
     * The form text is COMPARED in: canonical, plus the equivalences above.
     *
     * Never stored. A grader folds both sides and compares; the database keeps what the learner
     * would recognise.
     */
    public function fold(string $value): string
    {
        return strtr($this->canonical($value), self::FOLD_EQUIVALENCES);
    }
}
