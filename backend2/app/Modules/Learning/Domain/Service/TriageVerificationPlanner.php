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
     * "I know" faster than a person can read is a tell — but only for phrases. A single word is
     * taken in at a glance (three letters register in peripheral vision), so there is no "didn't
     * finish reading" state to catch: on-device the fastest honest swipe floors around ~490 ms
     * (paint + reaction + gesture), well above any word threshold worth setting, so a word floor
     * is either dead or, if raised to reach, flags every fast swipe — an inversion of its meaning.
     * Latency risk therefore applies to PHRASES only; word risk comes from cefr alone.
     */
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
        // Not applicable to single words (see PHRASE_MIN_READ_MS) — their risk is cefr-only.
        if (! $isPhrase) {
            return false;
        }

        // A missing measurement — no client latency (null), or a non-positive placeholder (0,
        // which no real swipe produces) — is neutral, never risk. Treating 0 as "impossibly
        // fast" would flag every "known" verdict and erase the whole benefit of triage.
        if ($latencyMs === null || $latencyMs <= 0) {
            return false;
        }

        return $latencyMs < self::PHRASE_MIN_READ_MS;
    }
}
