<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * IS THE KEY'S OWN TRANSLATION INSIDE THE LINE'S TRANSLATION?
 *
 * The Russian under a spoken line is the whole question the card asks: the learner reads it and
 * says the English. If the piece the card is about ({@see PlanSpeakingKey}) is not in that Russian,
 * the learner is asked to produce a word nothing on the screen pointed at, and «Не то» is the
 * card's fault. The owner's day 1 on 01.09 is where this was measured.
 *
 * ## Stems, because Russian has cases — and the stem is ADAPTIVE
 *
 * The card says «долгое проживание в новой стране» and the sentence says «для долгого проживания в
 * новой стране». Those are the same words and no substring search finds them. So a word is matched
 * by its STEM — a prefix — which is the crudest thing that works and is deliberately not a
 * morphology engine: the gate has to be explainable to whoever reads a rejected day.
 *
 * The length of that prefix is `min(N, длина − 2)` with a floor of 3, N per language; a key of
 * three letters or fewer is required to occur EXACTLY (решение архитектора, 03.09). A fixed five
 * was the rule until the owner's live day 2, and it cost four generations in a row: the key card
 * «quiet» translates as «тихий», whose root is three letters, so a five-letter stem swallowed the
 * whole word and demanded «тихий» inside «Здесь довольно тихо». «Тихое место мне подходит» and
 * «Это важно для меня» («важный») died the same way. The model wrote correct Russian four times
 * and the gate refused it four times — $0.17 of live money into one arithmetic mistake.
 *
 * The stem is cut from the KEY's word and the line's words are compared against that same length,
 * so both sides are always measured by one ruler. Two letters off the end is what a Russian ending
 * is worth («тихий» → «тих», «важный» → «важн»); the floor of three keeps a short word from
 * collapsing into a prefix that matches everything. It errs towards ACCEPTING — «стол» now shares
 * its stem with «столица» — and that is the direction this class chooses on purpose: a gate that
 * refuses a good day costs a paid re-run and then gets switched off, a gate that lets a weak line
 * through is caught by reading the day.
 *
 * A language with no stem length has the check SWITCHED OFF, exactly as the repair-move list does
 * for a language nobody has written yet: «German's rule is not written» must not read as «every
 * German day has a broken translation».
 *
 * ## ONE of the key's words, not all of them and not most of them
 *
 * The bar is a FLOOR, deliberately: at least one of the key's significant words is in the line's
 * translation. Short words are skipped — a preposition found proves nothing.
 *
 * Both stronger readings were tried and both refuse content that is right:
 *
 *   every word — «close to the city center» / «рядом с центром города» stands in a line translated
 *   «Есть ли что-нибудь рядом с центром?». That Russian is what a person says, and it drops
 *   «города» (owner's live day 1).
 *   a majority — the key's translation can carry words the sentence has no room for, and a card
 *   whose Russian names the thing once then goes on with the sentence scores under half.
 *
 * A gate that refuses a good day costs more than one that lets a weak day through: the second is
 * caught by reading the day, the first spends a paid repair call and then gets switched off. What
 * this floor still catches is the case it was written for and the only one that is unarguable —
 * NOTHING of the key is there. «место для аренды» against «жильё для долгого проживания в новой
 * стране» scores zero, and the learner reading that Russian has no way to know which word to say.
 */
final class TranslationKeyPresence
{
    /**
     * The LONGEST stem a word of this SUPPORT language is ever cut to — the `N` of
     * `min(N, длина − 2)`, per language the translation is written in.
     *
     * Five for Russian: a cap rather than a length, since v0.4's live day 2. A language absent from
     * the map switches the gate off rather than failing it.
     *
     * @var array<string, int>
     */
    public const DEFAULT_STEMS = [
        'ru' => 5,
        'uk' => 5,
        // TODO: подобрать, когда появится первый живой немецкий план. Отсутствие языка в карте =
        // проверка для него выключена (не срабатывает и не падает).
    ];

    /** Below this a word is a preposition or a particle, and finding it proves nothing. */
    private const SIGNIFICANT_LETTERS = 4;

    /**
     * The shortest stem there is. Below three letters a prefix stops being a root and starts being
     * a syllable that half the language begins with.
     */
    private const MIN_STEM = 3;

    /**
     * At this length and below there is no stem to take: the word IS its root, so it is required to
     * occur exactly. «дом» matched by «до» would be matched by «домой», «доска» and «до».
     */
    private const EXACT_AT_OR_BELOW = 3;

    /** @param array<string, int> $stems support language => stem length; {@see DEFAULT_STEMS} */
    public function __construct(private readonly array $stems = self::DEFAULT_STEMS) {}

    /** Is this language's rule written? A language without one is not judged at all. */
    public function judges(string $supportLang): bool
    {
        return $this->stemFor($supportLang) !== null;
    }

    /**
     * Does `$lineTranslation` carry `$keyTranslation`?
     *
     * True — including vacuously true — whenever the check cannot be made: no rule for the
     * language, nothing to look for, nowhere to look. A gate that cannot see must not refuse.
     */
    public function holds(string $supportLang, string $lineTranslation, string $keyTranslation): bool
    {
        $cap = $this->stemFor($supportLang);
        if ($cap === null) {
            return true;
        }

        $wanted = $this->significantWords($keyTranslation);
        if ($wanted === []) {
            return true;
        }

        $haystack = $this->words($lineTranslation);

        foreach ($wanted as $word) {
            if ($this->present($word, $haystack, $cap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this word of the key in the line, allowing for its ending?
     *
     * The stem is cut from THIS word and the line's words are read to the same length, so the two
     * sides are always compared by one ruler — that is what a per-word stem length requires and
     * what stemming both sides to a fixed five could not do.
     *
     * @param  list<string>  $haystack  the line translation's own words
     * @param  int  $cap  the longest stem this language ever takes {@see DEFAULT_STEMS}
     */
    private function present(string $word, array $haystack, int $cap): bool
    {
        $length = mb_strlen($word);
        if ($length <= self::EXACT_AT_OR_BELOW) {
            return in_array($word, $haystack, true);
        }

        $stem = max(self::MIN_STEM, min($cap, $length - 2));
        $prefix = mb_substr($word, 0, $stem);

        foreach ($haystack as $candidate) {
            if (mb_substr($candidate, 0, $stem) === $prefix) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key's words worth looking for.
     *
     * Significant ones when there are any; ALL of them when the key is made only of short words
     * («в срок», «по делу»), because «no significant word» must not mean «nothing to check». Words
     * and not stems since the stem is now cut per word {@see present()}.
     *
     * @return list<string>
     */
    private function significantWords(string $value): array
    {
        $words = $this->words($value);
        $significant = array_values(array_filter(
            $words,
            static fn (string $w): bool => mb_strlen($w) >= self::SIGNIFICANT_LETTERS,
        ));

        return $significant === [] ? $words : $significant;
    }

    /**
     * The comparable words of a string: lowercased, letters and digits only.
     *
     * A hyphenated word is split, so «долгосрочный» and «долго-срочный» stem alike and a card
     * written either way is found.
     *
     * @return list<string>
     */
    private function words(string $value): array
    {
        $split = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($value))) ?: [];

        return array_values(array_filter($split, static fn (string $w): bool => $w !== ''));
    }

    private function stemFor(string $lang): ?int
    {
        return $this->stems[mb_strtolower(substr(trim($lang), 0, 2))] ?? null;
    }
}
