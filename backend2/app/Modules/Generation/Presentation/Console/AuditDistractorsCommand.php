<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\AuditDistractors;
use App\Modules\Generation\Application\Command\AuditDistractorsHandler;
use Illuminate\Console\Command;

/**
 * Re-asks the validator's four questions of every stored distractor. Dry by default — `--apply` is
 * what writes.
 *
 * A sweep that exists only in a terminal history is a sweep nobody can re-run or verify; that is the
 * lesson of 46f9076, where the fix was real and the record of it was not. This is the same work as a
 * command: re-runnable, gated, and idempotent (a second pass finds nothing).
 */
final class AuditDistractorsCommand extends Command
{
    protected $signature = 'enrich:audit-distractors
        {--apply : write the fixes and deletions (default is a dry run)}
        {--v-notes : print a line per touched row}';

    protected $description = 'Ретро-аудит дистракторов валидатором: починить ярлык или удалить строку';

    public function handle(AuditDistractorsHandler $audit): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Ретро-аудит: ПРИМЕНЯЮ' : 'Ретро-аудит: сухой прогон');

        $outcome = $audit(new AuditDistractors($apply));

        $rows = [];
        foreach (['equality', 'dedup', 'noop', 'circular'] as $check) {
            $rows[] = [$check, (string) ($outcome->fixed[$check] ?? 0), (string) ($outcome->deleted[$check] ?? 0)];
        }
        $rows[] = ['<options=bold>итого</>', "<options=bold>{$outcome->totalFixed()}</>", "<options=bold>{$outcome->totalDeleted()}</>"];

        $this->table(['проверка', 'починено', 'удалено'], $rows);
        $this->line("просмотрено дистракторов: {$outcome->seen}");

        if ((bool) $this->option('v-notes')) {
            foreach ($outcome->notes as $note) {
                $this->line("  · {$note}");
            }
        }

        return self::SUCCESS;
    }
}
