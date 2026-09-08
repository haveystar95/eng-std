<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;
use App\Modules\Learning\Domain\ValueObject\MatchPolicy;
use App\Modules\Learning\Domain\ValueObject\SpeechGradingRules;
use App\Modules\Learning\Domain\ValueObject\SpokenCredit;
use App\Modules\Learning\Domain\ValueObject\SpokenVerdict;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

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

    /** The one shared definition of "the same words"; the coverage check uses it too. */
    public function __construct(
        private readonly LexicalNormalizer $normalizer = new LexicalNormalizer(),
        private readonly SpokenSuffixTolerance $suffixTolerance = new SpokenSuffixTolerance(),
        /** Where the recogniser cut the words — its guess, never the learner's. {@see SpokenWordBoundary} */
        private readonly SpokenWordBoundary $boundary = new SpokenWordBoundary(),
        /** ЗАЧЁТ ВСЕЙ ФРАЗЫ — одна функция на сервер и на экран (наряд SPEECH-2, Ч.3). */
        private readonly SpokenLine $spokenLine = new SpokenLine(),
        /** Пороги зачёта речи; едут из `config/learning.php → plan.speech` через провайдер. */
        private readonly SpeechGradingRules $speechRules = new SpeechGradingRules(),
    ) {}

    public function grade(Answer $answer, ExerciseMode $mode, ExpectedAnswer $expected, LatencyBaseline $baseline): Grade
    {
        // A key that asks to be MATCHED LOOSELY skips the three stages below entirely — they are
        // stages of equality, and this key is not asking for equality. Reached only by an answer
        // that came out of a recogniser ({@see SpokenCoverage} for why equality is the wrong bar,
        // {@see SpokenLine} for the three shapes «enough» now has).
        if ($expected->policy->isSpoken()) {
            return $this->judgeSpoken($answer->response, $expected)->isAccepted()
                ? $this->gradeCorrect($answer, $mode, $expected->isPhrase, $baseline)
                : Grade::Again;
        }

        $response = $this->normalizer->normalize($answer->response);

        // Stages 1 & 2: exact after normalisation, against the target OR any accepted synonym.
        // Speaking additionally forgives a dropped trailing -s/-es/-'s (QA-20): a recogniser eats
        // that sound far more than it invents a whole different word, so "salary expectation" for
        // "salary expectations" is the channel, not a lapse — see SpokenSuffixTolerance. No other
        // mode gets this: everywhere else a one-character difference is typed by the learner, not
        // heard by a microphone, and stage 3 already has its own (stricter) leniency for that.
        foreach ($expected->accepted as $candidate) {
            $normalizedCandidate = $this->normalizer->normalize($candidate);
            if ($response === $normalizedCandidate) {
                return $this->gradeCorrect($answer, $mode, $expected->isPhrase, $baseline);
            }
            if ($mode === ExerciseMode::Speaking && $this->suffixTolerance->equal($response, $normalizedCandidate)) {
                return $this->gradeCorrect($answer, $mode, $expected->isPhrase, $baseline);
            }
            // ...and a boundary the recogniser guessed differently: «without utilities» comes back
            // as «withoututilities» from a perfectly good reading of a two-word card, which is the
            // same channel fact as the eaten sibilant above and not a different memory
            // ({@see SpokenWordBoundary}). Speaking only, for the same reason: everywhere else the
            // spaces were typed by the learner.
            if ($mode === ExerciseMode::Speaking && $this->boundary->equalIgnoringBoundaries($response, $normalizedCandidate)) {
                return $this->gradeCorrect($answer, $mode, $expected->isPhrase, $baseline);
            }
        }

        // Stage 3: a single-character typo on a long-enough answer — correct, ceiling `hard`. Only
        // where there was typing to forgive: on a picked or assembled answer a one-character
        // difference is usually the very distinction the card tests ({@see forgivesTypos}).
        if ($mode->forgivesTypos()) {
            foreach ($expected->accepted as $candidate) {
                if ($this->isTypo($response, $this->normalizer->normalize($candidate))) {
                    return Grade::Hard;
                }
            }
        }

        return Grade::Again;
    }

    /**
     * ВЕРДИКТ ПРО СКАЗАННОЕ — публичный, потому что его читают двое: планировщик (через
     * {@see grade()}, которому нужно только «зачёт или нет») и телефон, которому нужен весь
     * вердикт целиком, чтобы сказать человеку «почти — не хватило: …». Одна функция, один
     * транскрипт, один ответ (наряд SPEECH-2, Ч.3.4).
     *
     * Несколько кандидатов — берётся ЛУЧШИЙ: у `read_aloud` и `whole_line` в `accepted` лежат
     * равноправные формы одной реплики, и «не подошла первая» ещё не вердикт.
     */
    public function judgeSpoken(string $response, ExpectedAnswer $expected): SpokenVerdict
    {
        if ($expected->policy === MatchPolicy::KeyAndRest) {
            return $this->spokenLine->judge(
                $response,
                // Реплики нет — судить «остальное» не по чему, и ключ становится всей целью.
                $expected->line ?? $expected->accepted[0],
                $expected->accepted,
                printed: false,
                rules: $this->speechRules,
            );
        }

        $printed = $expected->policy === MatchPolicy::ReadAloud;
        $best = null;
        foreach ($expected->accepted as $candidate) {
            $verdict = $this->spokenLine->judge($response, $candidate, [], $printed, $this->speechRules);
            if ($best === null || $this->outranks($verdict, $best)) {
                $best = $verdict;
            }
        }

        // `accepted` не бывает пустым ({@see ExpectedAnswer}), но тип этого не знает.
        return $best ?? new SpokenVerdict(SpokenCredit::Wrong, 0.0);
    }

    private function outranks(SpokenVerdict $candidate, SpokenVerdict $best): bool
    {
        $rank = static fn (SpokenVerdict $v): int => match ($v->credit) {
            SpokenCredit::Correct => 2,
            SpokenCredit::Almost => 1,
            SpokenCredit::Wrong => 0,
        };

        return $rank($candidate) > $rank($best)
            || ($rank($candidate) === $rank($best) && $candidate->coverage > $best->coverage);
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
        $r = $this->normalizer->stripArticle($response);
        $c = $this->normalizer->stripArticle($candidate);

        // Guard on the correct answer's length; levenshtein is byte-based, which is exact for
        // the latin target side and merely conservative elsewhere.
        if (mb_strlen($c) < self::MIN_TYPO_LENGTH) {
            return false;
        }

        return $r !== $c && levenshtein($r, $c) === 1;
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
