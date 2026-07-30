<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;

/**
 * Turns a client's Answer into a Grade for the scheduler. The mode decides how forgiving and
 * how generous grading is; the scheduler never learns which mode it came from — a grade is a
 * grade.
 *
 * Leniency is applied in explicit stages, because the order changes the grade:
 *   1. normalise (case, spacing, punctuation, optional article) — a match here is NOT a user
 *      error, so it earns the full grade;
 *   2. an accepted synonym/translation — same, full grade;
 *   3. a single-character typo on a long-enough answer — correct, but capped at `hard`.
 * Folding these into one "normalise and compare" step would let a typo slip through as `good`,
 * and a typo that wipes a 30-day interval erodes trust faster than forgetting does.
 */
final class AnswerGrader
{
    /** "Notably" above/below the personal median, when a median is known. */
    private const SLOW_FACTOR = 1.6;
    private const FAST_FACTOR = 0.5;

    /** Absolute fallbacks while the per-mode median is still unknown (ms). Provisional. */
    private const WORD_SLOW_MS = 8000;
    private const WORD_FAST_MS = 2000;
    private const PHRASE_SLOW_MS = 15000;
    private const PHRASE_FAST_MS = 4000;

    /** Typo leniency only for longer answers, else "cat"/"cut" and "pen"/"ten" pass as correct. */
    private const MIN_TYPO_LENGTH = 5;

    public function grade(Answer $answer, ExerciseMode $mode, ExpectedAnswer $expected, LatencyBaseline $baseline): Grade
    {
        $response = $this->normalize($answer->response);

        // Stages 1 & 2: exact after normalisation, against the target OR any accepted synonym.
        foreach ($expected->accepted as $candidate) {
            if ($response === $this->normalize($candidate)) {
                return $this->gradeCorrect($answer, $mode, $expected->isPhrase, $baseline);
            }
        }

        // Stage 3: a single-character typo on a long-enough answer — correct, ceiling `hard`.
        foreach ($expected->accepted as $candidate) {
            if ($this->isTypo($response, $this->normalize($candidate))) {
                return Grade::Hard;
            }
        }

        return Grade::Again;
    }

    private function gradeCorrect(Answer $answer, ExerciseMode $mode, bool $isPhrase, LatencyBaseline $baseline): Grade
    {
        // A hint means it was not recalled unaided.
        if ($answer->usedHint) {
            return Grade::Hard;
        }

        $speed = $this->speed($answer->latencyMs, $baseline, $isPhrase);
        if ($speed > 0) {
            return Grade::Hard; // slow
        }
        if ($speed < 0 && $mode->isProduction()) {
            return Grade::Easy; // fast, clean, produced from memory
        }

        // Recognition modes cap here — they can never reach `easy`.
        return $this->cap(Grade::Good, $mode->maxGrade());
    }

    /** -1 fast, 0 normal, +1 slow. Relative to the per-mode median, or absolute defaults. */
    private function speed(?int $latencyMs, LatencyBaseline $baseline, bool $isPhrase): int
    {
        if ($latencyMs === null) {
            return 0;
        }

        $median = $baseline->medianMs;
        if ($median !== null) {
            if ($latencyMs > $median * self::SLOW_FACTOR) {
                return 1;
            }

            return $latencyMs < $median * self::FAST_FACTOR ? -1 : 0;
        }

        $slow = $isPhrase ? self::PHRASE_SLOW_MS : self::WORD_SLOW_MS;
        $fast = $isPhrase ? self::PHRASE_FAST_MS : self::WORD_FAST_MS;
        if ($latencyMs > $slow) {
            return 1;
        }

        return $latencyMs < $fast ? -1 : 0;
    }

    private function isTypo(string $response, string $candidate): bool
    {
        $r = $this->stripArticle($response);
        $c = $this->stripArticle($candidate);

        // Guard on the correct answer's length; levenshtein is byte-based, which is exact for
        // the latin target side and merely conservative elsewhere.
        if (mb_strlen($c) < self::MIN_TYPO_LENGTH) {
            return false;
        }

        return $r !== $c && levenshtein($r, $c) === 1;
    }

    /** Lowercase, expand contractions, punctuation → space, whitespace collapsed. */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = $this->expandContractions($value);            // before punctuation strips the apostrophe
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $this->stripArticle(trim($value));
    }

    /**
     * Expand the common English contractions so "I'd like to withdraw" matches "I would like to
     * withdraw". A small curated set on purpose (the ambiguous ones like "'d" are the point of the
     * curation); it grows as real answers show what people actually type.
     */
    private function expandContractions(string $value): string
    {
        $value = str_replace(['’', '`'], "'", $value); // normalise apostrophe glyphs first
        $map = [
            "i'd" => 'i would', "i'll" => 'i will', "i'm" => 'i am', "i've" => 'i have',
            "you're" => 'you are', "you'd" => 'you would', "you'll" => 'you will', "you've" => 'you have',
            "we're" => 'we are', "we'd" => 'we would', "we'll" => 'we will', "we've" => 'we have',
            "they're" => 'they are', "they'd" => 'they would', "they'll" => 'they will', "they've" => 'they have',
            "it's" => 'it is', "that's" => 'that is', "there's" => 'there is', "let's" => 'let us',
            "don't" => 'do not', "doesn't" => 'does not', "didn't" => 'did not', "isn't" => 'is not',
            "aren't" => 'are not', "wasn't" => 'was not', "weren't" => 'were not', "can't" => 'cannot',
            "won't" => 'will not', "wouldn't" => 'would not', "couldn't" => 'could not', "shouldn't" => 'should not',
            "haven't" => 'have not', "hasn't" => 'has not', "hadn't" => 'had not',
        ];

        return (string) preg_replace_callback(
            "/\b[a-z]+'[a-z]+\b/",
            static fn (array $m): string => $map[$m[0]] ?? $m[0],
            $value,
        );
    }

    /** Leading article optional in both directions: "the bank" ↔ "bank". */
    private function stripArticle(string $normalized): string
    {
        return (string) preg_replace('/^(the|a|an)\s+/u', '', $normalized);
    }

    private function cap(Grade $grade, Grade $ceiling): Grade
    {
        return $this->rank($grade) <= $this->rank($ceiling) ? $grade : $ceiling;
    }

    private function rank(Grade $grade): int
    {
        return match ($grade) {
            Grade::Again => 0,
            Grade::Hard => 1,
            Grade::Good => 2,
            Grade::Easy => 3,
        };
    }
}
