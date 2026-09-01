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
 * ## Stems, because Russian has cases
 *
 * The card says «долгое проживание в новой стране» and the sentence says «для долгого проживания в
 * новой стране». Those are the same words and no substring search finds them. So a word is matched
 * by its STEM — the first N letters, N per language — which is the crudest thing that works and is
 * deliberately not a morphology engine: the gate has to be explainable to whoever reads a rejected
 * day, and «первые пять букв» is explainable.
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
     * How many letters of a word are its stem, per SUPPORT language — the language the translation
     * is written in.
     *
     * Five for Russian: long enough that «стол» and «столица» do not collide, short enough to
     * survive every case ending the language has. A language absent from the map switches the gate
     * off rather than failing it.
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
        $stem = $this->stemFor($supportLang);
        if ($stem === null) {
            return true;
        }

        $wanted = $this->significantWords($keyTranslation, $stem);
        if ($wanted === []) {
            return true;
        }

        $haystack = $this->stems($this->words($lineTranslation), $stem);

        foreach ($wanted as $word) {
            if (isset($haystack[$word])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key's words worth looking for, as stems.
     *
     * Significant ones when there are any; ALL of them when the key is made only of short words
     * («в срок», «по делу»), because «no significant word» must not mean «nothing to check».
     *
     * @return list<string>
     */
    private function significantWords(string $value, int $stem): array
    {
        $words = $this->words($value);
        $significant = array_values(array_filter(
            $words,
            static fn (string $w): bool => mb_strlen($w) >= self::SIGNIFICANT_LETTERS,
        ));

        return array_keys($this->stems($significant === [] ? $words : $significant, $stem));
    }

    /**
     * @param  list<string>  $words
     * @return array<string, true>  stem => present
     */
    private function stems(array $words, int $stem): array
    {
        $out = [];
        foreach ($words as $word) {
            $out[mb_substr($word, 0, $stem)] = true;
        }

        return $out;
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
