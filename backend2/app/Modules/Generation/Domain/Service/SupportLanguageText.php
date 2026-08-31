<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Shared\Domain\Service\LanguagePurity;

/**
 * IS THIS STRING WRITTEN IN THE LEARNER'S OWN LANGUAGE — with the exemptions a plan cannot live
 * without.
 *
 * The plain rule («ни одной буквы чужого алфавита») is right for ordinary content and wrong for a
 * plan in three specific ways, each of which was paid for once already on the owner's phone
 * ({@see PlanDayValidator::keyIsPure()} tells the same story about the day's keys):
 *
 * 1. **An ABBREVIATION** — two to five capital Latin letters standing on their own. `API`, `PHP`,
 *    `QA`. Decided by SHAPE, so there is no list to maintain: «работаю с QA-инженерами» is the only
 *    way a Russian speaker writes that sentence.
 * 2. **A CODE** — a token mixing digits and Latin letters: `14A`, `A320`, `B2`. Shape again, and it
 *    needs its own rule because the abbreviation shape starts at two letters and a seat number
 *    carries one. A travel plan died on `14A`, twice in one night.
 * 3. **`goal_terms`** — what the learner typed themselves: `Laravel`, `Docker`, `Zoom`. Mixed-case
 *    names the shape rules cannot see, and the one list that has to be handed in.
 *
 * Extracted from the day validator so the SKELETON can be judged by the same rule. Two copies of
 * this arithmetic is how one gate ends up refusing what the other accepts — and the outline gate is
 * the more dangerous of the two, because the outline gets ONE re-run and no more (v0.2.1): a false
 * positive there is a learner staring at «не получилось» after two paid calls.
 */
final class SupportLanguageText
{
    /**
     * Two to five capital Latin letters in a row, not glued to a longer Latin word on either side.
     *
     * The boundaries keep it from eating a name: the run has to stand on its own, so «BBC» is
     * exempt and the «Sha» of a mixed-case word is not.
     */
    private const ABBREVIATION = '/(?<![A-Za-z])[A-Z]{2,5}(?![A-Za-z])/u';

    /** A token that mixes digits and Latin letters — `14A`, `A320`, `B2`, `H1N1`, `PCR-2`. */
    private const CODE = '/(?<![A-Za-z])(?=[0-9A-Za-z-]*[0-9])(?=[0-9A-Za-z-]*[A-Za-z])[0-9A-Za-z]+(?:-[0-9A-Za-z]+)*(?![A-Za-z])/u';

    public function __construct(private readonly LanguagePurity $purity = new LanguagePurity()) {}

    /**
     * @param  list<string>  $exempt  tokens that stay verbatim in both languages — `goal_terms`,
     *                                and on a card its own `text`
     */
    public function isSupportLanguage(string $supportLang, string $value, array $exempt = []): bool
    {
        return $this->purity->foreignScriptLetters($supportLang, $this->strip($value, $exempt)) === [];
    }

    /**
     * The value with everything legitimately foreign removed. What is left has to be the learner's
     * own language, which is the rule the exemptions exist to keep enforceable.
     *
     * @param  list<string>  $exempt
     */
    public function strip(string $value, array $exempt = []): string
    {
        $stripped = (string) preg_replace([self::ABBREVIATION, self::CODE], ' ', $value);

        foreach ($exempt as $token) {
            $token = trim($token);
            if ($token !== '') {
                $stripped = str_ireplace($token, ' ', $stripped);
            }
        }

        return $stripped;
    }
}
