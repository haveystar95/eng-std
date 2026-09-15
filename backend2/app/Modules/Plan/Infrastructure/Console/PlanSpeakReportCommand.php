<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * `plan:speak-report {--plan=} {--day=}` — WHAT THE SERVER'S VOICE COST (TTS-2), read off `plan_line_audios`: the
 * characters of the lines, their price at the model's rate, the credits the vendor debited and the calls paid for
 * (distinct vendor request ids — the lines of one dialogue share one).
 *
 * - no options — every plan, one row each, and the total;
 * - `--plan` — that plan day by day (a scene day is one scene), and the total;
 * - `--plan --day` — that day by kind: partner lines, learner lines, phrases, fillers, words.
 *
 * Read-only.
 */
final class PlanSpeakReportCommand extends Command
{
    protected $signature = 'plan:speak-report {--plan= : one plan, day by day} {--day= : one day of that plan, by kind}';

    protected $description = 'What the server voice cost — characters, dollars, credits, calls — for every plan, a plan by day, or a day by kind';

    /** The kind of line a ref names, in SQL — the same names `plan:speak-backfill` counts by. */
    private const KIND = "CASE WHEN a.line_ref ~ '^x[0-9]+$' THEN 'partner lines'"
        ." WHEN a.line_ref ~ '^x[0-9]+b$' THEN 'learner lines'"
        ." WHEN a.line_ref ~ '^p[0-9]+$' THEN 'phrases'"
        ." WHEN a.line_ref ~ '^p[0-9]+\\.f[0-9]+$' THEN 'fillers'"
        ." ELSE 'words' END";

    private const TOTALS = 'count(*) as lines, coalesce(sum(a.characters), 0) as characters, coalesce(sum(a.cost_usd), 0) as usd, coalesce(sum(a.credits), 0) as credits, count(distinct coalesce(a.request_id, a.id)) as calls';

    public function handle(): int
    {
        $plan = $this->option('plan');
        $day = $this->option('day');
        if (is_string($plan) && $plan !== '' && ! Ulid::isValid($plan)) {
            $this->error("Not a plan id: {$plan}");

            return self::FAILURE;
        }
        if ($day !== null && (! is_string($plan) || $plan === '' || ! is_string($day) || ! ctype_digit($day))) {
            $this->error('--day needs --plan and a day number');

            return self::FAILURE;
        }

        if (! is_string($plan) || $plan === '') {
            $rows = self::lines()
                ->join('plans as p', 'p.id', '=', 's.plan_id')
                ->groupBy('p.id', 'p.created_at')
                ->orderBy('p.created_at', 'desc')
                ->selectRaw('p.id as what, '.self::TOTALS)
                ->get()->values()->all();
            $this->print('plan', $rows, self::lines()->selectRaw(self::TOTALS)->first());

            return self::SUCCESS;
        }

        if ($day === null) {
            $rows = self::lines()
                ->join('plan_days as d', static fn ($j) => $j->on('d.scene_id', '=', 's.id')->on('d.plan_id', '=', 's.plan_id'))
                ->where('s.plan_id', $plan)
                ->groupBy('d.number', 's.title_native')
                ->orderBy('d.number')
                ->selectRaw("'day ' || d.number || ' · ' || s.title_native as what, ".self::TOTALS)
                ->get()->values()->all();
            $this->print('day', $rows, self::lines()->where('s.plan_id', $plan)->selectRaw(self::TOTALS)->first());

            return self::SUCCESS;
        }

        $ofDay = static fn (): Builder => self::lines()
            ->join('plan_days as d', static fn ($j) => $j->on('d.scene_id', '=', 's.id')->on('d.plan_id', '=', 's.plan_id'))
            ->where('s.plan_id', $plan)
            ->where('d.number', (int) $day);
        $rows = $ofDay()
            ->groupByRaw(self::KIND)
            ->orderByRaw('min(a.created_at)')
            ->selectRaw(self::KIND.' as what, '.self::TOTALS)
            ->get()->values()->all();
        $this->print('kind', $rows, $ofDay()->selectRaw(self::TOTALS)->first());

        return self::SUCCESS;
    }

    private static function lines(): Builder
    {
        return DB::table('plan_line_audios as a')->join('plan_scenes as s', 's.id', '=', 'a.scene_id');
    }

    /**
     * @param  array<int, object>  $rows  each with `what`, `lines`, `characters`, `usd`, `credits`, `calls`
     */
    private function print(string $what, array $rows, ?object $total): void
    {
        $line = static fn (string $name, ?object $r): array => [
            $name,
            (int) ($r->lines ?? 0),
            (int) ($r->characters ?? 0),
            '$'.number_format((float) ($r->usd ?? 0), 4, '.', ''),
            (int) ($r->credits ?? 0),
            (int) ($r->calls ?? 0),
        ];
        $this->table(
            [$what, 'lines', 'characters', 'usd', 'credits', 'calls'],
            [...array_map(static fn (object $r): array => $line((string) ($r->what ?? ''), $r), $rows), $line('total', $total)],
        );
    }
}
