<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\RepairContentLanguage;
use App\Modules\Generation\Application\Command\RepairContentLanguageHandler;
use App\Modules\Generation\Application\Dto\LanguageRepairReport;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Console\Command;

/**
 * One-shot cleanup of learner-language fields that are not in the learner's language.
 *
 * Dry by default: it reads everything reachable and prints what it WOULD do. `--apply` is the
 * separate decision to spend model calls and write, because the list is the point — a sweep that
 * repaired 200 rows nobody looked at would be indistinguishable from a sweep that repaired 2.
 */
final class RepairContentLanguageCommand extends Command
{
    protected $signature = 'content:repair-language
        {--source=ru : learner language of the collections to sweep}
        {--apply : write the repairs (otherwise dry run)}
        {--orphans : sweep terms in NO collection instead of reachable ones — see docs/ua-audit.md}';

    protected $description = 'Find and repair learner-language content written in the wrong language';

    public function handle(RepairContentLanguageHandler $handler): int
    {
        $source = $this->option('source');
        $lang = new LanguageCode(is_scalar($source) ? (string) $source : 'ru');
        $apply = (bool) $this->option('apply');
        $orphans = (bool) $this->option('orphans');

        $report = $handler(new RepairContentLanguage($lang, $apply, $orphans));

        if ($report === []) {
            $scope = $orphans ? 'весь контент сирот' : 'весь достижимый контент';
            $this->info("Ничего не найдено: {$scope} на «{$lang->value}».");

            return self::SUCCESS;
        }

        $this->table(
            ['term', 'поле', 'было', 'стало', 'действие', 'почему'],
            array_map(static fn (LanguageRepairReport $r): array => [
                $r->termText,
                $r->field,
                mb_strimwidth($r->before, 0, 44, '…'),
                $r->after !== null ? mb_strimwidth($r->after, 0, 44, '…') : '—',
                $r->action,
                mb_strimwidth($r->note, 0, 52, '…'),
            ], $report),
        );

        $counts = array_count_values(array_map(static fn (LanguageRepairReport $r): string => $r->action, $report));
        foreach ($counts as $action => $n) {
            $this->line("  {$action}: {$n}");
        }

        $unfixable = $counts['unfixable'] ?? 0;
        if (! $apply) {
            $this->warn('Сухой прогон — ничего не записано. Повторить с --apply.');

            return self::SUCCESS;
        }

        // A row that could not be repaired is still on a phone, so this is not a clean exit.
        return $unfixable > 0 ? self::FAILURE : self::SUCCESS;
    }
}
