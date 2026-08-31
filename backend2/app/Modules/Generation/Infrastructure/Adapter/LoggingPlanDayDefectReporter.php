<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\PlanDayDefectReporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The log line is the durable record; the counter is for looking at.
 *
 * Two writes on purpose. The LOG carries the whole story — which plan, which day, which card, what
 * the model actually wrote — and survives everything; the COUNTER is one number a person can read
 * without grepping, and it lives in the cache because that is what a number nobody reconciles is
 * worth. If the cache is cleared the count restarts and the log does not, which is the right way
 * round.
 */
final class LoggingPlanDayDefectReporter implements PlanDayDefectReporter
{
    public function transliterationDropped(
        string $planId,
        int $dayIndex,
        string $text,
        ?string $raw,
        string $reason,
    ): void {
        // warning and not info: the learner cannot see this, cannot fix it, and the card they get
        // is missing a field the prompt was told was mandatory.
        Log::warning('Plan day card lost its reading hint; the day was written without it', [
            'counter' => self::TRANSLITERATION_DROPPED,
            'plan_id' => $planId,
            'day_index' => $dayIndex,
            'text' => $text,
            'transliteration' => $raw,
            'reason' => $reason,
        ]);

        // `add` first: some cache stores refuse to increment a key that does not exist yet, and a
        // counter that silently stays at zero is worse than no counter.
        Cache::add(self::TRANSLITERATION_DROPPED, 0);
        Cache::increment(self::TRANSLITERATION_DROPPED);
    }

    public function droppedTransliterations(): int
    {
        $value = Cache::get(self::TRANSLITERATION_DROPPED, 0);

        return is_numeric($value) ? (int) $value : 0;
    }
}
