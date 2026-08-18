<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\RecoverLostTerms;
use App\Modules\Generation\Application\Command\RecoverLostTermsHandler;
use App\Modules\Generation\Application\Dto\RecoveredTermReport;
use Illuminate\Console\Command;

/**
 * One-shot recovery for terms the positional-trim bug dropped from three specific collections
 * (fixed alongside this — see `DraftValidator`) before that fix landed. See
 * {@see RecoverLostTermsHandler} for the manifest and why it is hardcoded rather than searched.
 *
 * Dry by default, like every other one-off sweep in this module.
 */
final class RecoverLostTermsCommand extends Command
{
    protected $signature = 'generation:recover-lost-terms
        {--apply : import and attach the recovered terms (otherwise dry run)}';

    protected $description = 'Recover terms dropped by the positional-trim bug from their original generation logs';

    public function handle(RecoverLostTermsHandler $handler): int
    {
        $apply = (bool) $this->option('apply');
        $report = $handler(new RecoverLostTerms($apply));

        $this->table(
            ['коллекция', 'термин', 'статус', 'term_id/причина'],
            array_map(static fn (RecoveredTermReport $r): array => [
                $r->collectionTitle,
                $r->text,
                $r->status,
                $r->termId ?? $r->reason ?? '—',
            ], $report),
        );

        $counts = array_count_values(array_map(static fn (RecoveredTermReport $r): string => $r->status, $report));
        foreach ($counts as $status => $n) {
            $this->line("  {$status}: {$n}");
        }

        if (! $apply) {
            $this->warn('Сухой прогон — ничего не записано. Повторить с --apply.');

            return self::SUCCESS;
        }

        return ($counts['unrecoverable'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
