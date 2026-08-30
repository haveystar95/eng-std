<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * A2 — how much MACHINERY an expression carries.
 *
 * Deliberately not a CEFR level and not a frequency rank. CEFR says how advanced a word is in the
 * language; this says how much grammar a learner has to hold in their head to say the thing. They
 * are different questions and a plan orders its day by the second: «спина» and «Спина болит уже
 * неделю, и по утрам хуже» can both be A2 vocabulary, and only one of them is a sentence with a
 * tense, a conjunction and a comparison in it.
 *
 * ## The score
 *
 *   words × 2   the base — length is the single strongest signal there is
 *   + 1  past tense           + 1  modal
 *   + 2  subordinate clause   + 1  question
 *   + 1  negation
 *
 * Markers, not a parser. This is a SORT KEY, not a grammar checker: it has to be cheap enough to
 * run on every term of every day, and being wrong about one card costs a slightly odd position in a
 * list. A parser would be right more often and would be a dependency, a language table and a
 * maintenance burden for a number nobody reads.
 *
 * ## Frequency is zero, on purpose, and the seam is real
 *
 * The formula has a frequency term and its value is 0 for every language, because this app has no
 * frequency data: `terms.frequency_rank` is null on every row. The right answer is a per-language
 * frequency profile, and until one exists the honest thing is a stated zero with a place to put the
 * real number — not a plausible substitute (word length, letter counts) that would look like data
 * and rank words by nothing.
 *
 * ## Why Shared
 *
 * Two modules need the same number and neither owns it. Generation scores a term as it writes it
 * (`terms.difficulty_score`); Learning reads the score to order a day
 * ({@see \App\Modules\Learning\Domain\Service\PlanDayOrder}). Same argument as
 * {@see LanguagePurity}: one detector, two consumers, and a second copy is how two screens start
 * disagreeing about what «harder» means.
 *
 * en and de have marker tables. Any other language scores on length alone — an honest «no opinion
 * about your grammar» rather than English markers applied to Romanian, which would find «a» and
 * «are» inside words and rank by noise.
 */
final class DifficultyScorer
{
    /** Languages this class has actually been taught the markers of. */
    private const KNOWN = ['en', 'de'];

    /** @var array<string, list<string>> */
    private const MODALS = [
        'en' => ['can', 'could', 'may', 'might', 'must', 'shall', 'should', 'will', 'would', 'ought'],
        'de' => ['kann', 'könnte', 'darf', 'dürfte', 'muss', 'müsste', 'soll', 'sollte', 'will', 'möchte', 'wollte'],
    ];

    /** @var array<string, list<string>> */
    private const NEGATIONS = [
        'en' => ['not', "n't", 'never', 'no', 'nothing', 'nobody'],
        'de' => ['nicht', 'kein', 'keine', 'keinen', 'nie', 'niemand', 'nichts'],
    ];

    /**
     * Words that open a subordinate clause. Two points rather than one: a clause is the thing that
     * makes a sentence need to be PLANNED before it is said, which is exactly the wall a learner
     * below `conversational` hits.
     *
     * @var array<string, list<string>>
     */
    private const CLAUSE_MARKERS = [
        'en' => ['that', 'which', 'because', 'when', 'while', 'if', 'although', 'since', 'before', 'after', 'until', 'whether'],
        'de' => ['dass', 'weil', 'wenn', 'als', 'obwohl', 'damit', 'bevor', 'nachdem', 'während', 'ob'],
    ];

    /** Irregular past forms common enough to be worth naming; the rest is caught by `-ed`. */
    private const EN_IRREGULAR_PAST = [
        'was', 'were', 'had', 'did', 'went', 'said', 'made', 'took', 'came', 'saw', 'got',
        'gave', 'found', 'told', 'became', 'left', 'felt', 'put', 'brought', 'began', 'kept',
        'held', 'wrote', 'spent', 'built', 'sent', 'met', 'ran', 'paid', 'sat', 'spoke',
    ];

    /** Same, for German: the auxiliaries and modals whose preterite carries the tense. */
    private const DE_PAST = ['war', 'waren', 'hatte', 'hatten', 'wurde', 'wurden', 'ging', 'kam', 'sagte', 'machte'];

    public function score(string $lang, string $text): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $trimmed) ?: [$trimmed];
        $score = count($words) * 2;

        $code = strtolower(trim($lang));
        if (! in_array($code, self::KNOWN, true)) {
            // No marker table for this language: length only, and the score says so by not
            // pretending to know more. Frequency would go here too — see the class docblock.
            return $score + $this->frequencyPenalty($code, $trimmed);
        }

        $lower = mb_strtolower($trimmed);
        /** @var list<string> $tokens */
        $tokens = preg_split('/[^\p{L}\x{0027}\x{2019}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($this->hasPast($code, $lower, $tokens)) {
            $score += 1;
        }
        if ($this->containsAny($tokens, self::MODALS[$code])) {
            $score += 1;
        }
        if ($this->containsAny($tokens, self::CLAUSE_MARKERS[$code])) {
            $score += 2;
        }
        if (str_contains($trimmed, '?')) {
            $score += 1;
        }
        if ($this->hasNegation($code, $lower, $tokens)) {
            $score += 1;
        }

        return $score + $this->frequencyPenalty($code, $trimmed);
    }

    /**
     * The frequency term of the formula. Always 0 today, and the seam is the point.
     *
     * A per-language frequency profile is the missing input, not a missing line of code here: the
     * store has `terms.frequency_rank` and every row of it is null. When a profile arrives, it is
     * read here and nothing else in the plan changes.
     */
    private function frequencyPenalty(string $lang, string $text): int
    {
        unset($lang, $text);

        return 0;
    }

    /** @param list<string> $tokens */
    private function hasPast(string $lang, string $lower, array $tokens): bool
    {
        if ($lang === 'de') {
            // Perfekt («habe … gemacht») is the everyday past and shows up as the ge- participle.
            foreach ($tokens as $token) {
                if (str_starts_with($token, 'ge') && mb_strlen($token) > 4) {
                    return true;
                }
            }

            return $this->containsAny($tokens, self::DE_PAST);
        }

        if ($this->containsAny($tokens, self::EN_IRREGULAR_PAST)) {
            return true;
        }

        // `-ed`, and the perfect/continuous auxiliaries that put a sentence in the past.
        foreach ($tokens as $token) {
            if (mb_strlen($token) > 3 && str_ends_with($token, 'ed')) {
                return true;
            }
        }

        return str_contains($lower, 'has been') || str_contains($lower, 'have been');
    }

    /** @param list<string> $tokens */
    private function hasNegation(string $lang, string $lower, array $tokens): bool
    {
        // The contraction is not a word boundary away from its verb — «don't» tokenises whole.
        if ($lang === 'en' && (str_contains($lower, "n't") || str_contains($lower, 'n’t'))) {
            return true;
        }

        return $this->containsAny($tokens, self::NEGATIONS[$lang]);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $needles
     */
    private function containsAny(array $tokens, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $tokens, true)) {
                return true;
            }
        }

        return false;
    }
}
