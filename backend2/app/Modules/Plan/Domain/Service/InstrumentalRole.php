<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * THE ROLE WITH «С» — «Поговори с врачом» (кадр 37-5, наряд CONV-2, п. 12). The scene knows its partner's role in the
 * nominative only («Врач», «Сотрудник банка», «HR-менеджер»), and a title the client glues together from it reads
 * «Поговори с Врач»; the client printed «Поговори с собеседником» instead (CLIENT-CONV-1a, §5 п. 3).
 *
 * The instrumental of a Russian or Ukrainian role is written here by rule, over the words a role is made of: the
 * adjectives in front of the head noun agree with it («Нанимающий менеджер» → «нанимающим менеджером»), the head noun
 * takes its ending, and what follows it — a genitive («Сотрудник банка») — stays as it is. A first letter goes lower
 * case unless the next one is a capital too («HR-менеджер» keeps «HR»); a hyphen joins two nouns that both agree
 * («врач-терапевт» → «врачом-терапевтом») unless its first half is an abbreviation.
 *
 * WHERE THE ENDING HANGS ON STRESS, THE RULE DOES NOT GUESS. «врачом» but «сторожем», «продавцом» but «иностранцем»,
 * «секретарём» but «пекарем»: a word of such a shape is taken from the list below when it is there, and otherwise the
 * answer is null — the caller then says «с собеседником», which is plain and never wrong. A wrong ending in a title is
 * worse than a neutral word.
 */
final class InstrumentalRole
{
    /** @var array<string, array<string, string>> words whose ending is known by heart, not by rule */
    private const KNOWN = [
        'ru' => [
            'врач' => 'врачом', 'сторож' => 'сторожем', 'продавец' => 'продавцом', 'кузнец' => 'кузнецом',
            'жилец' => 'жильцом', 'секретарь' => 'секретарём', 'вратарь' => 'вратарём', 'пекарь' => 'пекарем',
            'библиотекарь' => 'библиотекарем', 'лекарь' => 'лекарем', 'товарищ' => 'товарищем', 'гость' => 'гостем',
            'мать' => 'матерью', 'дочь' => 'дочерью', 'повар' => 'поваром', 'парикмахер' => 'парикмахером',
        ],
        'uk' => [
            'лікар' => 'лікарем', 'секретар' => 'секретарем', 'кухар' => 'кухарем', 'пекар' => 'пекарем',
            'бібліотекар' => 'бібліотекарем', 'господар' => 'господарем', 'продавець' => 'продавцем',
            'касир' => 'касиром', 'маляр' => 'малярем', 'столяр' => 'столяром', 'гість' => 'гостем',
        ],
    ];

    private const VOWELS = 'аеёиоуыэюяіїєґ';

    public static function of(string $lang, string $role): ?string
    {
        $role = trim((string) preg_replace('/\s+/u', ' ', $role));
        if ($role === '' || ! in_array($lang, ['ru', 'uk'], true)) {
            return null;
        }
        $words = explode(' ', $role);
        $out = [];
        $headDone = false;
        foreach ($words as $i => $word) {
            if ($headDone) {
                $out[] = $word;

                continue;
            }
            $isLast = $i === count($words) - 1;
            $lower = self::lowered($word, $i === 0);
            if (! $isLast && ($adjective = self::adjective($lang, $lower, lone: false)) !== null) {
                $out[] = $adjective;

                continue;
            }
            // The head: an adjective standing alone is a noun here («дежурный», «полицейский», «горничная»).
            $head = ($isLast && $out === [] ? self::adjective($lang, $lower, lone: true) : null) ?? self::nounWithHyphen($lang, $lower);
            if ($head === null) {
                return null;
            }
            $out[] = $head;
            $headDone = true;
        }

        return $headDone ? implode(' ', $out) : null;
    }

    /** The preposition «с» / «со» (ru) or «з» / «зі» (uk) the instrumental takes, by the sound it stands before. */
    public static function with(string $lang, string $instrumental): string
    {
        $first = mb_strtolower(mb_substr($instrumental, 0, 1));
        $second = mb_strtolower(mb_substr($instrumental, 1, 1));
        $cluster = $second !== '' && ! str_contains(self::VOWELS, $second) && preg_match('/\p{Cyrillic}/u', $second) === 1;
        if ($lang === 'uk') {
            return $cluster && in_array($first, ['з', 'с', 'ш', 'щ', 'ж', 'ч'], true) ? 'зі' : 'з';
        }

        return $cluster && in_array($first, ['с', 'з', 'ш', 'ж', 'щ'], true) ? 'со' : 'с';
    }

    private static function lowered(string $word, bool $first): string
    {
        if (! $first || mb_strlen($word) < 2) {
            return $word;
        }
        $a = mb_substr($word, 0, 1);
        $b = mb_substr($word, 1, 1);

        return mb_strtolower($b) === $b ? mb_strtolower($a).mb_substr($word, 1) : $word;
    }

    private static function nounWithHyphen(string $lang, string $word): ?string
    {
        if (! str_contains($word, '-')) {
            return self::noun($lang, $word);
        }
        $parts = explode('-', $word);
        $last = array_pop($parts);
        $tail = self::noun($lang, $last);
        if ($tail === null) {
            return null;
        }
        $head = [];
        foreach ($parts as $part) {
            // An abbreviation or a Latin word in front («HR-», «IT-») does not agree; a Cyrillic noun does.
            if (preg_match('/^\p{Cyrillic}+$/u', $part) !== 1 || mb_strtoupper($part) === $part) {
                $head[] = $part;

                continue;
            }
            $agreed = self::noun($lang, $part);
            if ($agreed === null) {
                return null;
            }
            $head[] = $agreed;
        }

        return implode('-', [...$head, $tail]);
    }

    /**
     * A word that is an adjective or a participle, in the instrumental; null when it does not look like one.
     *
     * In front of another word any adjectival ending will do — the noun after it is the head. A word standing ALONE
     * must end the way only adjectives end («-ный», «-щий», «-ский», «-ая»…) and be six letters at least: «гений» and
     * «герой» end in «-ний» and «-ой» too, and they are nouns.
     */
    private static function adjective(string $lang, string $word, bool $lone): ?string
    {
        if (preg_match('/^\p{Cyrillic}+$/u', $word) !== 1 || mb_strlen($word) < 4) {
            return null;
        }
        if ($lone && (mb_strlen($word) < 6 || preg_match($lang === 'uk'
            ? '/(н|ськ|цьк|ов|ев|л|т|р|ч|щ|ш|ж|к|г|х)(ий|ій)$|(н)[ая]$/u'
            : '/(н|щ|ч|ш|ж|к|г|х|ск|цк|в|л|т|р)(ый|ий|ой)$|[ая]я$/u', $word) !== 1)) {
            return null;
        }
        $endings = $lang === 'uk'
            ? ['ий' => 'им', 'ій' => 'ім', 'а' => 'ою', 'я' => 'ьою']
            : ['ый' => 'ым', 'ий' => 'им', 'ой' => 'ым', 'ая' => 'ой', 'яя' => 'ей'];
        foreach ($endings as $ending => $instrumental) {
            if (! str_ends_with($word, $ending)) {
                continue;
            }
            // A Ukrainian noun in -а/-я is not an adjective; only the adjectival -на/-ня of a feminine one is.
            if ($lang === 'uk' && in_array($ending, ['а', 'я'], true) && preg_match('/н[ая]$/u', $word) !== 1) {
                return null;
            }

            return mb_substr($word, 0, -mb_strlen($ending)).$instrumental;
        }

        return null;
    }

    /** A noun in the instrumental singular; null where its ending would be a guess. */
    private static function noun(string $lang, string $word): ?string
    {
        if (preg_match('/\p{Cyrillic}/u', $word) !== 1) {
            return $word; // a Latin word (a brand, an abbreviation) does not decline
        }
        $lower = mb_strtolower($word);
        $known = self::KNOWN[$lang][$lower] ?? null;
        if ($known !== null) {
            return $lower === $word ? $known : $word; // a capitalised word left as written is an abbreviation
        }

        $last = mb_substr($lower, -1);
        $stem = mb_substr($word, 0, -1);

        return $lang === 'uk' ? self::ukNoun($word, $lower, $last, $stem) : self::ruNoun($word, $lower, $last, $stem);
    }

    private static function ruNoun(string $word, string $lower, string $last, string $stem): ?string
    {
        return match (true) {
            in_array($last, ['о', 'е', 'и', 'у', 'ю', 'э'], true) => $word,         // портье, кофе: do not decline
            str_ends_with($lower, 'тель') => $stem.'ем',                            // преподавателем, арендодателем
            $last === 'ь' => null,                                                 // секретарём / пекарем: stress
            $last === 'й' => $stem.'ем',                                           // гением
            str_ends_with($lower, 'ец') => null,                                   // продавцом / иностранцем: stress
            in_array($last, ['ж', 'ш', 'ч', 'щ', 'ц'], true) => null,              // врачом / сторожем: stress
            str_ends_with($lower, 'ия') => $stem.'ей',                              // «-ией»
            $last === 'я' => $stem.'ей',                                           // няней
            $last === 'а' && in_array(mb_substr($lower, -2, 1), ['ж', 'ш', 'ч', 'щ', 'ц'], true) => $stem.'ей', // продавщицей
            $last === 'а' => $stem.'ой',                                           // медсестрой, баристой
            default => $word.'ом',                                                 // врач is known; агентом, менеджером
        };
    }

    private static function ukNoun(string $word, string $lower, string $last, string $stem): ?string
    {
        return match (true) {
            in_array($last, ['о', 'е', 'і', 'у', 'ю'], true) => $word,
            str_ends_with($lower, 'ець') => null,                                  // продавцем is known; the rest by stress
            $last === 'ь' => $stem.'ем',                                           // вчителем
            $last === 'й' => $stem.'єм',                                           // водієм
            str_ends_with($lower, 'ар') || str_ends_with($lower, 'яр') => null,    // лікарем / столяром: known or a guess
            in_array($last, ['ж', 'ч', 'ш', 'щ'], true) => $word.'ем',             // сторожем, ткачем: the mixed group
            str_ends_with($lower, 'ія') => $stem.'єю',
            $last === 'я' => $stem.'ею',                                           // продавчинею
            $last === 'а' && in_array(mb_substr($lower, -2, 1), ['ж', 'ч', 'ш', 'щ'], true) => $stem.'ею',
            $last === 'а' => $stem.'ою',                                           // медсестрою
            default => $word.'ом',                                                 // агентом, менеджером
        };
    }
}
