<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * THE LEARNER'S-LANGUAGE TEXT RULES THE LESSON VALIDATOR READS — for the Cyrillic native languages
 * the plan serves (ru, uk, be); for any other native language every question here answers «not
 * checked» rather than a guess.
 *
 * Two questions: do two texts talk about the same thing (listening questions against the lines of the
 * visit — by shared content words, a root match forgiving the endings of an inflected language), and
 * does a learner line carry a gendered past form after «я» («я работал», «я не была»), which the
 * prompt asks to avoid when the learner's gender is unknown. Heuristic by design — the canon says so.
 */
final class NativeWords
{
    private const CHECKED = ['ru', 'uk', 'be'];

    private const FUNCTION = [
        'и', 'в', 'во', 'на', 'с', 'со', 'у', 'к', 'ко', 'о', 'об', 'по', 'за', 'из', 'от', 'до', 'для', 'при',
        'про', 'над', 'под', 'без', 'не', 'ни', 'ли', 'же', 'бы', 'что', 'чтобы', 'как', 'так', 'это', 'этот',
        'эта', 'эти', 'тот', 'та', 'те', 'он', 'она', 'оно', 'они', 'его', 'её', 'ее', 'их', 'ему', 'ей', 'им',
        'него', 'нее', 'неё', 'них', 'нам', 'вам', 'мне', 'мы', 'вы', 'я', 'ты', 'меня', 'тебя', 'вас', 'нас',
        'а', 'но', 'или', 'если', 'когда', 'где', 'куда', 'там', 'тут', 'здесь', 'уже', 'ещё', 'еще', 'очень',
        'только', 'да', 'нет', 'вот', 'все', 'всё', 'весь', 'вся', 'свой', 'своя', 'свои', 'мой', 'моя', 'мои',
        'ваш', 'ваша', 'ваши', 'наш', 'наша', 'наши', 'какой', 'какая', 'какое', 'какие', 'который', 'которая',
        'чем', 'чём', 'кто', 'быть', 'есть', 'будет', 'можно', 'нужно', 'надо',
    ];

    public static function isChecked(string $nativeLang): bool
    {
        return in_array(strtolower($nativeLang), self::CHECKED, true);
    }

    /**
     * Content words of a native text, lower-cased.
     *
     * @return list<string>
     */
    public static function content(string $text): array
    {
        return array_values(array_filter(
            Words::tokens($text),
            static fn (string $t): bool => ! in_array($t, self::FUNCTION, true) && mb_strlen($t) > 1,
        ));
    }

    /** How many content words of `$a` have a word of the same root in `$b`. */
    public static function shared(string $a, string $b): int
    {
        $theirs = self::content($b);
        $hits = 0;
        foreach (array_unique(self::content($a)) as $word) {
            foreach ($theirs as $other) {
                if (self::sameRoot($word, $other)) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits;
    }

    /**
     * The same word, or two forms of it: both at least four letters long, sharing all but the last two
     * letters of the shorter one («пояснице» — «поясница», «неделю» — «неделя»).
     */
    public static function sameRoot(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $min = min(mb_strlen($a), mb_strlen($b));
        if ($min < 4) {
            return false;
        }
        $prefix = 0;
        while ($prefix < $min && mb_substr($a, $prefix, 1) === mb_substr($b, $prefix, 1)) {
            $prefix++;
        }

        return $prefix >= max(4, $min - 2);
    }

    /**
     * The gendered past forms a learner line says about the learner: a past-tense verb right after
     * «я» (a «не», «уже», «раньше», «тоже», «сам», «сама» may stand between).
     *
     * @return list<string>
     */
    public static function genderedPast(string $text): array
    {
        preg_match_all(
            '/(?<![\p{L}])я\s+(?:(?:не|уже|раньше|тоже|сам|сама|давно|недавно)\s+)?(\p{Cyrillic}{2,}[аяеиыуоёі]л(?:а|ся|ась)?)(?![\p{L}])/u',
            mb_strtolower($text),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }
}
