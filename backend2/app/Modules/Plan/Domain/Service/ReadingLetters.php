<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * A READING WITH ITS CYRILLIC WORDS WRITTEN IN THE LETTERS ITS LEARNER READS — one rule for every reading the plan keeps: the
 * lesson's as the parser reads it (наряды LANG-1 and LANG-1b §10.3, {@see \App\Modules\Plan\Domain\Lesson\LessonParser}) and
 * the readings already stored in the words, phrases and dealt cards of a plan (`plan:clean-text`, наряд LANG-1b, последнее).
 *
 * In every run of letters (and their combining marks) that holds at least one Cyrillic letter:
 *
 * - a Latin letter drawn like a Cyrillic one becomes that Cyrillic letter ({@see self::CYRILLIC_TWINS}), and a Latin vowel
 *   with its acute becomes the Cyrillic vowel with the combining acute U+0301 ({@see self::CYRILLIC_STRESSED}): «до лекáжа» →
 *   «до лека́жа», «___ ми пасуe» → «___ ми пасуе», «нюмэро дё телефoн» → «нюмэро дё телефон» (наряд LANG-1);
 * - a letter of ANOTHER Cyrillic alphabet becomes the letter of the readings it stands for ({@see self::CYRILLIC_ALIENS},
 *   наряд LANG-1b §10.3): «а аҗута́» → «а ажута́» — the owner's ru→ro day read «a ajuta» with the Tatar «җ» three times;
 * - with the learner's language named (the parser, наряд GEN-4b §3), a Latin «ú» / «í» becomes that alphabet's stressed /i/ —
 *   «и́» in Russian, «і́» in Ukrainian and Belarusian ({@see self::CYRILLIC_STRESSED_I}): «а сэ нумú» → «а сэ нуми́».
 *   `plan:clean-text`, which reads stored rows without their plan, mends only what every Cyrillic reading shares.
 *
 * Why a machine mends them rather than a repair: a repair is a paid call, two a stage, and `pronunciation.foreign_script` sends
 * its cards there first (a warning since GEN-4b §3; fatal beyond the stage's repairs) — and these are not letters of another writing — they are the same letter from the other table, or a letter of a sister alphabet drawn
 * almost as the learner reads it; only the code point is wrong. The twins were every one of the seven `foreign_script`
 * findings of the LANG-1 scouting days and four of the eleven of the ru→en days replayed (`docs/research/lang-1/baseline.md`);
 * the Tatar «җ» is Cyrillic, so `foreign_script` let it through and only the warning `pronunciation.script` counted it.
 *
 * What it does NOT touch. A run with no Cyrillic letter in it stays as written: the Latin reading of a learner who reads Latin
 * letters, and a Latin word among Cyrillic ones («SMS-ку» keeps its «SMS»; «X-рэй» its «X» — the hyphen ends a run). A letter
 * with no twin stays too («пасуje» keeps its «j»). Letters of OTHER writings — Georgian «პლ», Armenian «ֆ» and «պր», the Greek
 * «θ» of the ru→en days — are mended by no table and stay real findings of `foreign_script` for the repair to rewrite. And a
 * reading the pattern cannot read at all — bytes that are not UTF-8, which a hand-built payload can carry though a decoded
 * JSON cannot — is kept as written, never emptied.
 */
final class ReadingLetters
{
    /**
     * The Latin letters a Cyrillic word may be written with by mistake, each with the Cyrillic letter it is drawn like — one
     * shape in two tables of the alphabet (наряд LANG-1): lower-case a e o c p x y k, capital A E O C P X Y B H K M T.
     */
    private const CYRILLIC_TWINS = [
        'a' => 'а', 'e' => 'е', 'o' => 'о', 'c' => 'с', 'p' => 'р', 'x' => 'х', 'y' => 'у', 'k' => 'к',
        'A' => 'А', 'E' => 'Е', 'O' => 'О', 'C' => 'С', 'P' => 'Р', 'X' => 'Х', 'Y' => 'У',
        'B' => 'В', 'H' => 'Н', 'K' => 'К', 'M' => 'М', 'T' => 'Т',
    ];

    /**
     * The letters of OTHER Cyrillic alphabets a Cyrillic reading may be written with by mistake — Kazakh, Tatar, Bashkir,
     * Mongolian letters no learner's language of the plan has — each with the letter of the readings it stands for (наряд
     * LANG-1b §10.3): җ→ж, ғ→г, қ→к, ә→э, ү→у, ұ→у, ң→н, һ→х, ө→о, and their capitals.
     */
    private const CYRILLIC_ALIENS = [
        'җ' => 'ж', 'ғ' => 'г', 'қ' => 'к', 'ә' => 'э', 'ү' => 'у', 'ұ' => 'у', 'ң' => 'н', 'һ' => 'х', 'ө' => 'о',
        'Җ' => 'Ж', 'Ғ' => 'Г', 'Қ' => 'К', 'Ә' => 'Э', 'Ү' => 'У', 'Ұ' => 'У', 'Ң' => 'Н', 'Һ' => 'Х', 'Ө' => 'О',
    ];

    /**
     * A Latin vowel written with its acute in one character — the stress of a Cyrillic reading drawn from the Latin table
     * («лекáжа») — as its canonical decomposition read through {@see self::CYRILLIC_TWINS}: the Cyrillic vowel, then the
     * combining acute U+0301 the readings mark stress with («лека́жа»).
     */
    private const CYRILLIC_STRESSED = [
        'á' => "а\u{0301}", 'é' => "е\u{0301}", 'ó' => "о\u{0301}", 'ý' => "у\u{0301}",
        'Á' => "А\u{0301}", 'É' => "Е\u{0301}", 'Ó' => "О\u{0301}", 'Ý' => "У\u{0301}",
    ];

    /**
     * THE STRESSED «I» OF THE LEARNER'S OWN ALPHABET (наряд GEN-4b §3): a Latin «ú» or «í» with its acute inside a Cyrillic
     * reading stands for the vowel that reading writes as /i/ — «и́» for a Russian learner, «і́» for a Ukrainian or a
     * Belarusian one (Belarusian has no «и»). Which one depends on the learner's language, so it is read only when the
     * caller names it: the e2e day of GEN-4 failed on «а сэ нумú» (Romanian «a se numi», read «а сэ нуми́»). Letters of other
     * writings — Hebrew, Arabic, Devanagari — are no twins and stay findings for the repair.
     */
    private const CYRILLIC_STRESSED_I = [
        'ru' => ['ú' => "и\u{0301}", 'í' => "и\u{0301}", 'Ú' => "И\u{0301}", 'Í' => "И\u{0301}"],
        'uk' => ['ú' => "і\u{0301}", 'í' => "і\u{0301}", 'Ú' => "І\u{0301}", 'Í' => "І\u{0301}"],
        'be' => ['ú' => "і\u{0301}", 'í' => "і\u{0301}", 'Ú' => "І\u{0301}", 'Í' => "І\u{0301}"],
    ];

    /**
     * The reading with its Cyrillic words in the letters of the readings; nothing else of it changes. `$native` — the
     * learner's language code, when known: its own twins are read too ({@see self::CYRILLIC_STRESSED_I}); without it only
     * the twins every Cyrillic reading shares.
     */
    public static function mended(string $reading, ?string $native = null): string
    {
        $table = (self::CYRILLIC_STRESSED_I[$native ?? ''] ?? []) + self::CYRILLIC_STRESSED + self::CYRILLIC_TWINS + self::CYRILLIC_ALIENS;

        return preg_replace_callback(
            '/[\p{L}\p{M}]+/u',
            static fn (array $run): string => preg_match('/\p{Cyrillic}/u', $run[0]) === 1 ? strtr($run[0], $table) : $run[0],
            $reading,
        ) ?? $reading;
    }
}
