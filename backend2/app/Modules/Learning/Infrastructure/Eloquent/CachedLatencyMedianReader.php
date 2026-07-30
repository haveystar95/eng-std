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

    public function medianFor(UserId $user, ExerciseMode $mode): LatencyBaseline
    {
        return Cache::remember($this->key($user, $mode), self::TTL_SECONDS, fn (): LatencyBaseline => $this->compute($user, $mode));
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
