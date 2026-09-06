<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * IS THIS CARD BASIC VOCABULARY — a thing the plan must not spend a slot on?
 *
 * Канон §7, verbatim: «Стоп-список базового (числа, дни недели, семья, время, цвета, местоимения,
 * be/have/go, a little / enough / much / again / slowly и подобное) — фатально от уровня „Понимаю
 * простое“ и выше, для начинающих — warning.»
 *
 * The rule earns its place from what a day costs. A day-scene holds about twenty-five units and is
 * the only material the learner will meet before the appointment; a slot spent on «Monday» is a
 * reply about their child's fever they will not have. And the level split is not a softening: a
 * `zero` learner genuinely meets «one», «two», «Monday» for the first time, so the same card that
 * is a wasted slot at B1 is the lesson at A0.
 *
 * ## A LIST AND NOT A RULE — which is why it lives in config
 *
 * The same shape {@see PlanDayValidator::DEFAULT_REPAIR_MARKERS} has and for the same reason: it
 * will be wrong, and being wrong about a word list must not be a code change. A language with no
 * list has the check SWITCHED OFF rather than failing it — «German's list is not written» must not
 * read as «every German day is clean».
 *
 * Matched on the WHOLE card, normalised: a card is basic when its own text is one of these, never
 * when it merely contains one. «a little» is basic; «a little more time, please» is a line and is
 * not this rule's business.
 */
final class BasicVocabulary
{
    /**
     * The one list that exists, for the one target language a plan has ever run in.
     *
     * Numbers are matched by SHAPE as well ({@see isNumber()}) — a list cannot hold every number,
     * and «числа» is the first entry of the canon's own list.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_STOP_LIST = [
        'en' => [
            // числа словами
            'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
            'eleven', 'twelve', 'twenty', 'thirty', 'forty', 'fifty', 'hundred', 'thousand',
            'first', 'second', 'third',
            // дни недели и время
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
            'today', 'tomorrow', 'yesterday', 'morning', 'evening', 'night', 'day', 'week',
            'month', 'year', 'hour', 'minute', 'time',
            // семья
            'mother', 'father', 'son', 'daughter', 'child', 'children', 'wife', 'husband',
            'brother', 'sister', 'family', 'parents', 'baby',
            // цвета
            'red', 'blue', 'green', 'yellow', 'black', 'white', 'brown', 'grey', 'gray',
            // местоимения и служебное
            'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
            'my', 'your', 'his', 'our', 'their', 'this', 'that', 'these', 'those',
            'yes', 'no', 'please', 'thanks', 'thank you', 'hello', 'goodbye', 'sorry',
            // артикли и базовые предлоги — служебные слова, которые карточкой не бывают ни на
            // каком уровне, и без которых «связка из базовых слов» не ловится (решение владельца
            // 03.09: список неполон против канона §7, чинится конфигом, а не гейтом)
            'a', 'an', 'the',
            'for', 'in', 'on', 'at', 'to', 'of', 'with', 'from', 'by',
            // be / have / go и их формы
            'be', 'am', 'is', 'are', 'was', 'were', 'been', 'have', 'has', 'had',
            'go', 'goes', 'went', 'gone', 'do', 'does', 'did', 'can', 'want', 'need',
            'get', 'gets', 'got', 'see', 'sees', 'saw', 'come', 'comes', 'came',
            // из канона поимённо
            'a little', 'little', 'enough', 'much', 'many', 'again', 'slowly', 'very', 'more',
            'good', 'bad', 'big', 'small', 'here', 'there', 'now', 'later',
        ],
        'de' => [],
    ];

    /**
     * The level from which a basic card is REFUSED rather than counted.
     *
     * «Понимаю простое» is `basic`; `zero` is below it. Ordered, so a level the enum grows later
     * lands on the strict side by default — the safe direction for a rule about wasted slots.
     *
     * @var list<string>
     */
    private const LENIENT_LEVELS = ['zero'];

    /**
     * Articles, per target language — the whole of the `card.chunk_article_pair` rule's vocabulary.
     *
     * A list of its own even though every article is also in the stop list: the chunk gate asks a
     * different question — «is the FIRST word an article» — and answering it from the stop list
     * would turn «for the desk» into an article pair the day a preposition was added there.
     *
     * German is unwritten for the same reason its stop list is: a language whose list is missing is
     * NOT judged, and «немецкий список не написан» must never read as «каждый немецкий день чист».
     *
     * @var array<string, list<string>>
     */
    private const ARTICLES = [
        'en' => ['a', 'an', 'the'],
        'de' => [],
    ];

    /** @param array<string, list<string>> $stopList target language => words; {@see DEFAULT_STOP_LIST} */
    public function __construct(private readonly array $stopList = self::DEFAULT_STOP_LIST) {}

    /** Is this language's list written at all? A language without one is not judged. */
    public function judges(string $targetLang): bool
    {
        return $this->listFor($targetLang) !== [];
    }

    /** Is the card's own text a piece of basic vocabulary? */
    public function isBasic(string $targetLang, string $text): bool
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return false;
        }

        if (self::isNumber($normalized)) {
            return true;
        }

        $list = $this->listFor($targetLang);
        if (in_array($normalized, $list, true)) {
            return true;
        }

        // A PLURAL OF A LISTED WORD IS THE SAME WORD — «days» beside «day», «weeks» beside «week»
        // (наряд GEN-1: a live ro→en day carded «days»). The list holds base forms; the one
        // inflection English puts on a noun this list is about is the plural, and a stop list that
        // has to enumerate both is a stop list that will be missing one of them.
        foreach (['es', 's'] as $suffix) {
            if (str_ends_with($normalized, $suffix)
                && mb_strlen($normalized) > mb_strlen($suffix) + 2
                && in_array(mb_substr($normalized, 0, -mb_strlen($suffix)), $list, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is a basic card FATAL at this level, or merely counted?
     *
     * The canon's own split, and the only place it is stated.
     */
    public function isFatalAt(string $level): bool
    {
        return ! in_array(mb_strtolower(trim($level)), self::LENIENT_LEVELS, true);
    }

    /**
     * IS EVERY WORD OF THIS CARD BASIC — the «связка из базовых слов» check (канон §7, 03.09).
     *
     * Read only by the chunk gate {@see PlanDayValidator::CHUNK_IS_BASIC}. «see it» is two words
     * the learner already has, glued together by this one sentence; a chunk is supposed to be a
     * piece of language that lives OUTSIDE the sentence it was found in («front desk», «make an
     * appointment»). One basic word inside a chunk is fine and common — «an appointment» is barred
     * by the article rule, not by this one — so the test is ALL, never ANY.
     *
     * ## What is deliberately not in the list
     *
     * «work» and «works». «works for me» is a real spoken chunk and the owner refused to lose it
     * (решение 03.09); the price is that «works for» — a fragment cut off from its object — is not
     * caught mechanically at all. That one is held by the prompt text of `plan_day.v0.4.1`, and the
     * limit is deliberate rather than overlooked.
     */
    public function allBasic(string $targetLang, string $text): bool
    {
        $normalized = self::normalize($text);
        if ($normalized === '' || $this->listFor($targetLang) === []) {
            return false;
        }

        $words = explode(' ', $normalized);
        foreach ($words as $word) {
            if (! $this->isBasic($targetLang, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is this word an article of the target language?
     *
     * A separate list from the stop list even though every article is also in it: the chunk gate
     * asks a different question — «is the FIRST word an article» — and answering it by scanning the
     * whole stop list would make «for the desk» an article pair the moment a preposition was added.
     */
    public function isArticle(string $targetLang, string $word): bool
    {
        $lang = mb_strtolower(trim($targetLang));
        $articles = self::ARTICLES[$lang] ?? self::ARTICLES[mb_substr($lang, 0, 2)] ?? [];

        return in_array(self::normalize($word), $articles, true);
    }

    /**
     * A card that is a bare number — «14», «2026», «3.5».
     *
     * By shape, because no list can hold every number and «числа» is the canon's first entry.
     * A number in a plan lives on the `numbers` shelf, inside the line it is heard in, never as a
     * word card of its own.
     */
    private static function isNumber(string $normalized): bool
    {
        return preg_match('/^\d+([.,]\d+)?$/u', $normalized) === 1;
    }

    /** @return list<string> */
    private function listFor(string $targetLang): array
    {
        $lang = mb_strtolower(trim($targetLang));

        return $this->stopList[$lang] ?? $this->stopList[mb_substr($lang, 0, 2)] ?? [];
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — the day validator's own normalisation. */
    private static function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
