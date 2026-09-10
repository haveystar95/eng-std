<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Dto\CheckCounterRow;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

/** One row per (prompt version, check, action), incremented in place. */
final class EloquentCheckCounters implements CheckCounters
{
    public function record(string $promptVersion, array $findings): void
    {
        $hits = [];
        foreach ($findings as $finding) {
            $key = $finding->check.'|'.$finding->action->value;
            $hits[$key] = ($hits[$key] ?? 0) + 1;
        }

        foreach ($hits as $key => $count) {
            [$check, $action] = explode('|', $key, 2);
            DB::statement(
                'INSERT INTO plan_check_counters (id, prompt_version, check_name, action, hits, updated_at) VALUES (?, ?, ?, ?, ?, ?) '
                .'ON CONFLICT (prompt_version, check_name, action) DO UPDATE SET hits = plan_check_counters.hits + EXCLUDED.hits, updated_at = EXCLUDED.updated_at',
                [Ulid::generate(), $promptVersion, $check, $action, $count, now()],
            );
        }
    }

    public function all(): array
    {
        $rows = DB::table('plan_check_counters')->orderBy('prompt_version')->orderBy('check_name')->orderBy('action')->get();

        return array_values($rows->map(static fn (object $r): CheckCounterRow => new CheckCounterRow(
            (string) $r->prompt_version, (string) $r->check_name, (string) $r->action, (int) $r->hits,
        ))->all());
    }
}
