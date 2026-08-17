<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LatencyBaseline;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CachedLatencyMedianReader implements LatencyMedianReader
{
    /** Safety-net TTL; the real freshness comes from explicit invalidation on projection. */
    private const TTL_SECONDS = 86400;

    /**
     * Never cache the value object itself. A serialized domain object in the cache store survives
     * the class that defines it: once it moves, is renamed or changes shape, the stored row
     * unserializes to `__PHP_Incomplete_Class`, the return type check fails, and EVERY
     * `POST /reviews/batch` answers 500 until the 24 h TTL runs out. That is exactly what
     * happened in production. So the cache holds a plain int, and anything else found under the
     * key is treated as poison: dropped and recomputed, so a bad entry heals on first read
     * instead of waiting out the TTL.
     *
     * 0 is the "not enough samples" sentinel — `Cache::remember` re-computes on null, so null
     * cannot be stored, and a real median is clamped to >= 1 by [LatencyBaseline::median].
     */
    public function medianFor(UserId $user, ExerciseMode $mode): LatencyBaseline
    {
        $key = $this->key($user, $mode);
        $cached = Cache::get($key);

        if (! is_int($cached)) {
            if ($cached !== null) {
                Cache::forget($key);
            }
            $cached = $this->compute($user, $mode)->medianMs ?? 0;
            Cache::put($key, $cached, self::TTL_SECONDS);
        }

        return $cached > 0 ? LatencyBaseline::median($cached) : LatencyBaseline::insufficient();
    }

    public function forget(UserId $user, ExerciseMode $mode): void
    {
        Cache::forget($this->key($user, $mode));
    }

    private function compute(UserId $user, ExerciseMode $mode): LatencyBaseline
    {
        $row = DB::table('reviews')
            ->where('user_id', $user->value)
            ->where('exercise_mode', $mode->value)
            ->where('is_correct', true)
            ->where('is_practice', false)
            ->whereNotNull('latency_ms')
            ->selectRaw('count(*) as n, percentile_cont(0.5) within group (order by latency_ms) as median')
            ->first();

        if ($row === null || $row->median === null || (int) $row->n < LatencyBaseline::MIN_SAMPLES) {
            return LatencyBaseline::insufficient();
        }

        return LatencyBaseline::median((int) round((float) $row->median));
    }

    private function key(UserId $user, ExerciseMode $mode): string
    {
        return "learning:latency_median:{$user->value}:{$mode->value}";
    }
}
