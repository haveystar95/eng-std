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

    /** Number words of ru / uk / be, ordinals and «раз» included — whole words. */
    private const NUMBER = '/^(?:\d[\p{L}\d:.,]*|один|одн[аоуиы]\w*|два|две|двух|двум|двумя|трое|тр[её]х|тр[её]м|тремя|три|четыр\w*|пят[ьи]|пятью|пятнадцат\w*|пятьдесят\w*|пятьсот|шест[ьи]|шестью|шестнадцат\w*|шестьдесят\w*|сем[ьи]|семнадцат\w*|семьдесят\w*|восем\w*|восьм\w*|девят\w*|девяност\w*|десят\w*|одиннадцат\w*|двенадцат\w*|тринадцат\w*|двадцат\w*|тридцат\w*|сорок\w*|сто|ста|сотн\w*|двест\w*|трист\w*|четырест\w*|тысяч\w*|миллион\w*|половин\w*|полтор\w*|перв\w*|втор[оаы]\w*|трет\w*|четв[её]рт\w*|пят[ыо]\w*|шест[ыо]\w*|седьм\w*|раз|раза|адзін|адна|дзве|тры|чатыры|пяць|шэсць|сем|восем|дзевяць|дзесяць|чотири|п[\'’]ять|шість|сім|вісім|дев[\'’]ять)$/u';

    /** Time and duration words of ru / uk / be — units, parts of the day, days, months, «вчера», «назад», «через». */
    private const TIME = '/^(?:назад|спустя|тому|через|раньше|позже|скоро|недавно|давно|сейчас|потом|раніше|пізніше|зараз|потім|секунд\w*|минут\w*|хвилин\w*|хвілін\w*|час|часа|часов|часу|годин\w*|гадзін\w*|сутк\w*|суток|ден[ьи]|дня|дней|дн[её]м|дні|днів|дзень|дзён|недел\w*|тиж\w*|тыдз\w*|тыдн\w*|месяц\w*|місяц\w*|год|года|году|годы|лет|рік|роки|років|гады|гадоў|утр[оау]\w*|ранок|ранку|вранці|раніц\w*|вечер\w*|вечір|вечора|ввечері|вечар\w*|ноч\w*|ніч|полдень|полночь|вчера|вчерашн\w*|вчора|учора|ўчора|сегодня|сегодняшн\w*|сьогодні|сёння|завтра|завтрашн\w*|заўтра|позавчера|послезавтра|понедельник\w*|вторник\w*|сред[ауы]|четверг\w*|пятниц\w*|суббот\w*|воскресень\w*|январ\w*|феврал\w*|март\w*|апрел\w*|ма[йя]|июн\w*|июл\w*|август\w*|сентябр\w*|октябр\w*|ноябр\w*|декабр\w*|выходн\w*)$/u';

    public static function isChecked(string $nativeLang): bool
    {
        return in_array(strtolower($nativeLang), self::CHECKED, true);
    }

    public const VALUE_NUMBER_OR_TIME = 'number_or_time';

    public const VALUE_MIXED = 'mixed';

    public const VALUE_OTHER = 'other';

    /**
     * What kind of value a short answer names, as far as words tell without meaning: `number_or_time` — nothing
     * but numbers and time words («три дня», «со вчера», «в 9 утра», «шестью»); `other` — no number and no time
     * word at all («поясница», «после футбола»); `mixed` — a count or a time of a thing («две воды», «14A у окна»),
     * which may stand beside either.
     */
    public static function valueKind(string $text): string
    {
        $words = array_values(array_filter(Words::tokens($text), static fn (string $t): bool => ! in_array($t, self::FUNCTION, true)));
        $counted = count(array_filter($words, static fn (string $w): bool => preg_match(self::NUMBER, $w) === 1 || preg_match(self::TIME, $w) === 1));

        return match (true) {
            $words !== [] && $counted === count($words) => self::VALUE_NUMBER_OR_TIME,
            $counted === 0 => self::VALUE_OTHER,
            default => self::VALUE_MIXED,
        };
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
