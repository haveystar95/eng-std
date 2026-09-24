<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;

/**
 * THE FORMS OF ONE WORD (наряд BACK-TAILS-2 §2): the bases a word may be a form of — the word itself, its irregular base
 * (`irregular_forms`: «has» → «have», «me» → «i»), and what every regular ending the pack knows leaves of it
 * (`inflection_rules`: «works» → «work»), each with its irregular base too. Two words are one when their bases meet. A
 * language without `irregular_forms` has only the word itself.
 */
final class WordBases
{
    /** @return list<string> */
    public static function of(string $word, LanguagePack $pack): array
    {
        if (! $pack->has('irregular_forms')) {
            return [$word];
        }
        $irregular = $pack->map('irregular_forms');
        $out = [$word];
        foreach ($pack->has('inflection_rules') ? $pack->map('inflection_rules') : [] as $rule) {
            if (! is_array($rule) || ! is_string($rule[0] ?? null) || ! is_string($rule[1] ?? null)) {
                continue;
            }
            $base = preg_replace($rule[0], $rule[1], $word, 1, $count);
            if ($count > 0 && is_string($base) && $base !== '') {
                $out[] = $base;
            }
        }
        foreach ($out as $form) {
            $base = $irregular[$form] ?? null;
            if (is_string($base) && $base !== '') {
                $out[] = $base;
            }
        }

        return array_values(array_unique($out));
    }

    /** Are the two words forms of one word? */
    public static function meet(string $a, string $b, LanguagePack $pack): bool
    {
        return $a === $b || array_intersect(self::of($a, $pack), self::of($b, $pack)) !== [];
    }
}
