<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Learning\Domain\ValueObject\VerificationPlan;

/**
 * Decides when to check a "I know this" swipe. The job is to catch a mis-classification
 * *fast*, so a risky verdict gets a short interval; an obvious one is trusted for a long
 * time. This is not SRS scheduling — a known term has no ease or interval to grow.
 *
 * All thresholds are provisional constants in one place, to be moved by the real share of
 * failed checks, never by intuition.
 */
final class TriageVerificationPlanner
{
    /** Risky verdict: re-check soon (spec allows 7–10 days). */
    private const EARLY_CHECK_DAYS = 7;

    /** Obvious verdict: trust it for a long time before re-checking. */
    private const LONG_INTERVAL_DAYS = 90;

    /**
     * "I know" faster than a person can read is a tell. The floor differs by kind: 400 ms on a
     * single word is plausible, on a four-word phrase it means they never finished reading.
     */
    private const WORD_MIN_READ_MS = 300;
    private const PHRASE_MIN_READ_MS = 900;

    public function plan(?CefrLevel $termLevel, CefrLevel $userLevel, ?int $latencyMs, bool $isPhrase): VerificationPlan
    {
        $risky = $this->aboveUserLevel($termLevel, $userLevel) || $this->tooFastToBeRead($latencyMs, $isPhrase);

        return new VerificationPlan(
            dueInDays: $risky ? self::EARLY_CHECK_DAYS : self::LONG_INTERVAL_DAYS,
            risky: $risky,
        );
    }

    /** A term above the user's level is a claim worth checking. Unknown level (null) is neutral. */
    private function aboveUserLevel(?CefrLevel $termLevel, CefrLevel $userLevel): bool
    {
        return $termLevel !== null && $termLevel->isHigherThan($userLevel);
    }

    private function tooFastToBeRead(?int $latencyMs, bool $isPhrase): bool
    {
        // A missing measurement — no client latency (null), or a non-positive placeholder (0,
        // which no real swipe produces) — is neutral, never risk. Treating 0 as "impossibly
        // fast" would flag every "known" verdict and erase the whole benefit of triage.
        if ($latencyMs === null || $latencyMs <= 0) {
            return false;
        }

        return $latencyMs < ($isPhrase ? self::PHRASE_MIN_READ_MS : self::WORD_MIN_READ_MS);
    }
}
