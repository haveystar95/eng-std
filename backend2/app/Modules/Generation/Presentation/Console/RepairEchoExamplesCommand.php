<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\RepairEchoExamples;
use App\Modules\Generation\Application\Command\RepairEchoExamplesHandler;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs the examples that teach nothing in an EXISTING collection — the ones generated before the
 * validator started refusing them (QA-7).
 *
 * `--collection` is required and there is no run-everything mode, for the same reason the enrichment
 * backfill has none: this spends money per term, and a person should be able to look at the deck
 * first. `--dry-run` counts and spends nothing, which is the intended first invocation.
 */
final class RepairEchoExamplesCommand extends Command
{
    protected $signature = 'examples:repair-echo
        {--collection= : collection id (ULID), REQUIRED — there is no run-everything mode}
        {--dry-run : list what would be repaired and spend nothing}';

    protected $description = 'Regenerate examples that merely repeat their term (or are missing) in one collection';

    public function handle(RepairEchoExamplesHandler $repair): int
    {
        $option = $this->option('collection');
        $collectionId = is_string($option) ? $option : '';
        if ($collectionId === '') {
            $this->error('--collection is required.');

            return self::FAILURE;
        }

        // The owner is read from the collection rather than passed in: the spend ledger is per user,
        // and the person to bill is the person whose deck this is.
        $ownerId = DB::table('collections')->where('id', $collectionId)->value('owner_id');
        if (! is_string($ownerId)) {
            $this->error("No collection {$collectionId}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $repair(new RepairEchoExamples(
            actorId: UserId::fromString($ownerId),
            collectionId: CollectionId::fromString($collectionId),
            dryRun: $dryRun,
        ));

        $this->line("examined:       {$report->examined}");
        $this->line("needing repair: {$report->needingRepair}");

        if ($dryRun) {
            foreach ($report->repairedTermIds as $termId) {
                $text = DB::table('terms')->where('id', $termId)->value('text');
                $this->line("  · {$termId}  {$text}");
            }
            $this->info('Dry run — nothing was generated and nothing was spent.');

            return self::SUCCESS;
        }

        $this->line("repaired:       {$report->repaired()}");
        $this->line("tokens:         {$report->tokensIn} in / {$report->tokensOut} out");
        $this->line("cost:           \${$report->costUsd}");

        foreach ($report->failures as $failure) {
            $this->warn("  ! {$failure['term_id']}: {$failure['error']}");
        }

        return $report->failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
