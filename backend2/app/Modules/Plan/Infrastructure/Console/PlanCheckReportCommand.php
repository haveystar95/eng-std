<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Domain\Check\LessonCodes;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

/**
 * `plan:check-report {--since=}` — WHAT THE DAY'S CHECKS FIND ON THE DAYS THEY BUILT (наряд CHECK-1; наряд GEN-4 — the two
 * stages' rules and the seam judge), read off the findings stored beside every lesson (`plan_scenes.checks_json`, what was
 * left after the repairs — or, of a failed day, what failed it) and the counters (`plan_check_counters`).
 *
 * One row a code: how many findings the stored lessons hold, how many of them are of a fatal code, on how many days the
 * code stands, how many of those days failed with it (`fail_reason`), the share of all days with a lesson the code
 * stands on, and the counters over every attempt — `counted / gated / failed`. Under the table, three examples a code:
 * the finding's text, the plan and the day. `--since` reads the days written from that moment (`generated_at`, or the
 * build's start of a failed day); the counters know no time and are printed whole.
 *
 * Under the examples, THE COUNTER THAT IS NO FINDING — a judge that did not answer (`judge.unavailable`): nothing of it is
 * in `checks_json`, so the table cannot show it.
 *
 * Read-only.
 */
final class PlanCheckReportCommand extends Command
{
    protected $signature = 'plan:check-report {--since= : only the days written since this moment (any date PHP reads)}';

    protected $description = 'What the day\'s checks find — by code: findings, fatal ones, days, failed days, share, counters, three examples';

    private const EXAMPLES = 3;

    public function handle(): int
    {
        $since = $this->option('since');
        $from = null;
        if (is_string($since) && $since !== '') {
            try {
                $from = new DateTimeImmutable($since);
            } catch (Throwable) {
                $this->error("Not a moment PHP reads: {$since}");

                return self::FAILURE;
            }
        }

        $days = $this->days($from);
        $rows = $this->findings($from);
        $counters = $this->counters();

        if ($rows === []) {
            $this->line($days === 0 ? 'No day with a lesson'.($from === null ? '' : ' since '.$from->format(DATE_ATOM)).'.' : "Nothing found on {$days} days.");

            return self::SUCCESS;
        }

        /** @var array<string, array{findings: int, days: array<string, true>, failed: array<string, true>, examples: list<string>}> $byCode */
        $byCode = [];
        foreach ($rows as $row) {
            $code = (string) $row->code;
            $byCode[$code] ??= ['findings' => 0, 'days' => [], 'failed' => [], 'examples' => []];
            $byCode[$code]['findings']++;
            $byCode[$code]['days'][(string) $row->scene_id] = true;
            if ((string) $row->lesson_status === 'failed' && in_array($code, self::failedOn((string) $row->fail_reason), true)) {
                $byCode[$code]['failed'][(string) $row->scene_id] = true;
            }
            if (count($byCode[$code]['examples']) < self::EXAMPLES) {
                $byCode[$code]['examples'][] = sprintf(
                    '%s — «%s», day %s «%s»%s',
                    (string) $row->detail,
                    (string) $row->plan_title,
                    $row->day_number === null ? '?' : (string) $row->day_number,
                    (string) $row->scene_title,
                    (string) $row->lesson_status === 'failed' ? ' (failed)' : '',
                );
            }
        }
        ksort($byCode);

        $this->table(
            ['code', 'findings', 'fatal', 'days', 'failed days', 'share of days', 'counted / gated / failed'],
            array_map(static fn (string $code, array $c): array => [
                $code,
                $c['findings'],
                LessonCodes::isFatal($code) ? $c['findings'] : 0,
                count($c['days']),
                count($c['failed']),
                $days === 0 ? '—' : number_format(count($c['days']) * 100 / $days, 1).' %',
                implode(' / ', [$counters[$code]['counted'] ?? 0, $counters[$code]['gated'] ?? 0, $counters[$code]['failed'] ?? 0]),
            ], array_keys($byCode), $byCode),
        );
        $this->line("Days with a lesson: {$days}".($from === null ? '' : ' since '.$from->format(DATE_ATOM)).'. Counters are over every attempt and every date.');
        $this->newLine();
        foreach ($byCode as $code => $c) {
            $this->line($code.(LessonCodes::isFatal($code) ? ' (fatal)' : ''));
            foreach ($c['examples'] as $example) {
                $this->line('  · '.$example);
            }
        }
        $this->newLine();
        $this->line('The counter that is no finding (counted / gated / failed, every attempt and every date):');
        $code = LessonCodes::JUDGE_UNAVAILABLE;
        $this->line(sprintf('  %s: %s', $code, implode(' / ', [$counters[$code]['counted'] ?? 0, $counters[$code]['gated'] ?? 0, $counters[$code]['failed'] ?? 0])));

        return self::SUCCESS;
    }

    /** How many days (scenes) have a lesson written — ready or failed — since `$from`. */
    private function days(?DateTimeImmutable $from): int
    {
        $query = DB::table('plan_scenes as s')->whereIn('s.lesson_status', ['ready', 'failed']);
        if ($from !== null) {
            $query->whereRaw('coalesce(s.generated_at, s.build_started_at, s.updated_at) >= ?', [$from->format(DATE_ATOM)]);
        }

        return $query->count();
    }

    /**
     * Every stored finding with its day: newest days first, so the examples are the latest.
     *
     * @return array<int, stdClass>
     */
    private function findings(?DateTimeImmutable $from): array
    {
        $query = DB::table('plan_scenes as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->leftJoin('plan_days as d', static fn ($j) => $j->on('d.scene_id', '=', 's.id')->on('d.plan_id', '=', 's.plan_id'))
            ->crossJoin(DB::raw('lateral jsonb_array_elements(s.checks_json) with ordinality as f(finding, n)'))
            ->whereIn('s.lesson_status', ['ready', 'failed'])
            ->orderByRaw('coalesce(s.generated_at, s.build_started_at, s.updated_at) desc, s.id, d.number, f.n')
            ->selectRaw("s.id as scene_id, s.lesson_status, s.fail_reason, s.title_native as scene_title, p.title_native as plan_title, d.number as day_number, f.finding->>'code' as code, f.finding->>'detail' as detail");
        if ($from !== null) {
            $query->whereRaw('coalesce(s.generated_at, s.build_started_at, s.updated_at) >= ?', [$from->format(DATE_ATOM)]);
        }

        return $query->get()->values()->all();
    }

    /**
     * The counters of every prompt version added up: code → action → hits.
     *
     * @return array<string, array<string, int>>
     */
    private function counters(): array
    {
        $out = [];
        foreach (DB::table('plan_check_counters')->groupBy('check_name', 'action')->selectRaw('check_name, action, sum(hits) as hits')->get() as $row) {
            $out[(string) $row->check_name][(string) $row->action] = (int) $row->hits;
        }

        return $out;
    }

    /**
     * The codes a failed day names — `fatal: frame.count, vocab.not_found` ({@see LessonCodes::failReason()}).
     *
     * @return list<string>
     */
    private static function failedOn(string $reason): array
    {
        if (! str_starts_with($reason, 'fatal:')) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', substr($reason, strlen('fatal:'))))));
    }
}
