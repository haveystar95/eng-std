<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Console;

use App\Modules\Learning\Application\Command\ArchiveEndedPlanTerms;
use App\Modules\Learning\Application\Command\ArchiveEndedPlanTermsHandler;
use Illuminate\Console\Command;

/**
 * `plan:archive-terms` — the ended-plan sweep, at a prompt.
 *
 * Everything this command knows is how to ask and how to print. The sweep itself, and every word of
 * why it exists, is {@see ArchiveEndedPlanTermsHandler}.
 *
 * DRY BY DEFAULT: `--apply` is what writes. The fleet is one person, this runs across every account,
 * and a sweep that reports before it writes is the only kind worth running on the only copy of the
 * data that exists.
 */
final class ArchivePlanTermsCommand extends Command
{
    protected $signature = 'plan:archive-terms {--apply : write the change; without it the command only reports}';

    protected $description = 'Take out of the study pool the words of plans that are completed or abandoned.';

    public function handle(ArchiveEndedPlanTermsHandler $sweep): int
    {
        $report = $sweep(new ArchiveEndedPlanTerms(apply: (bool) $this->option('apply')));

        if ($report->rows === []) {
            $this->info('Nothing to archive: no enrolled pair belongs to an ended plan.');

            return self::SUCCESS;
        }

        $this->table(
            ['user', 'plan(s)', 'что сделано', 'снято с лестницы'],
            array_map(static fn (array $row): array => [
                $row['user_id'],
                $row['title'],
                $row['status'],
                (string) $row['pairs'],
            ], $report->rows),
        );

        if (! $this->option('apply')) {
            $this->warn(
                "Dry run: {$report->unenrolled} pair(s) would leave the pool, "
                . "{$report->stripped} would keep their place and lose only the plan's marker. "
                . 'Re-run with --apply to write it.',
            );

            return self::SUCCESS;
        }

        $this->info(
            "Archived: {$report->unenrolled} pair(s) left the study pool; "
            . "{$report->stripped} kept their place and lost only the plan's marker. "
            . 'Plans, days, cards and reviews untouched.',
        );

        return self::SUCCESS;
    }
}
