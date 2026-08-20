<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\RegenerateShowcase;
use App\Modules\Generation\Application\Command\RegenerateShowcaseHandler;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Dto\ShowcaseCostEstimate;
use App\Modules\Generation\Application\Dto\ShowcaseRegenReport;
use Illuminate\Console\Command;

/**
 * Re-writes the catalogue's old cores with the current prompt, and rebuilds the machinery on them.
 *
 * THIS COMMAND SPENDS MONEY AND REPLACES LIVE CONTENT. It is the only writer in the codebase allowed
 * to overwrite a term's key, which is the whole point of it — see
 * {@see \App\Modules\Vocabulary\Application\Port\TermCoreWriter}. Take a backup
 * (`scripts/db-backup.sh`) before a real run, start with `--limit` and READ the result before
 * widening it, and use `--dry-run` first: it calls nothing, writes nothing, and prints the bill.
 *
 * ## Batch API
 *
 * The dry run prints what the same work would cost through OpenAI's Batch API (50% off for work that
 * can wait, which a catalogue sweep can). That path is NOT implemented here: it is an asynchronous
 * protocol — upload a JSONL of requests, poll a batch id for up to 24 hours, download and re-pair the
 * results — which needs its own persistence for the batch id and its own resume story, and none of
 * that can be exercised without spending real money against the real endpoint. The saving is printed
 * so the decision to build it is taken against a number.
 */
final class RegenerateShowcaseCommand extends Command
{
    /** Read a page at a time so a Ctrl-C never loses more than the term in flight. */
    private const PAGE = 25;

    protected $signature = 'generation:regenerate-showcase
        {--prompt=* : prompt versions to sweep (default: legacy, v8, v9). NOT --version: Symfony reserves that globally and artisan would print the framework version instead of running this.}
        {--limit=0 : stop after this many terms (0 = no cap). Start small and read the result.}
        {--after= : resume cursor — the term id a previous pass finished on}
        {--lang=ru : which translation to rewrite}
        {--no-mechanics : rewrite cores only, leave the станок to a later pass}
        {--dry-run : count the terms and price the run; calls nothing, writes nothing}
        {--out= : write the report (markdown) to this path}';

    protected $description = 'Regenerate the cores of terms written by an old prompt version, then rebuild their machinery';

    public function handle(RegenerateShowcaseHandler $regenerate, GenerationStackConfig $stack): int
    {
        $versions = $this->versions();
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info(sprintf(
            'Перегенерация витрины · версии %s · ядро %s (%s, форма enrich) · механика %s (%s, форма machinery)',
            implode(', ', $versions),
            $stack->coreModel,
            $stack->corePromptVersion,
            $this->option('no-mechanics') ? '—' : $stack->mechanicsModel,
            $stack->mechanicsPromptVersion,
        ));

        $report = $regenerate(new RegenerateShowcase(
            promptVersions: $versions,
            afterId: $this->stringOption('after'),
            limit: $dryRun ? $limit : min($limit > 0 ? $limit : self::PAGE, self::PAGE),
            dryRun: $dryRun,
            translationLang: $this->stringOption('lang') ?? 'ru',
            withMechanics: ! (bool) $this->option('no-mechanics'),
        ));

        if ($dryRun) {
            $this->dryRunReport($report);

            return self::SUCCESS;
        }

        $this->runReport($report);

        return $this->write($report) ? self::SUCCESS : self::FAILURE;
    }

    private function dryRunReport(ShowcaseRegenReport $report): void
    {
        $estimate = $report->estimate;
        $this->line("Терминов со старым паспортом: <options=bold>{$report->pending}</>");

        if (! $estimate instanceof ShowcaseCostEstimate) {
            $this->warn('Оценка стоимости недоступна.');

            return;
        }

        $this->table(['статья', 'токены in', 'токены out', '$'], [
            ['ядро', (string) $estimate->coreTokensIn, (string) $estimate->coreTokensOut, $this->usd($estimate->coreUsd)],
            ['механика', (string) $estimate->mechanicsTokensIn, (string) $estimate->mechanicsTokensOut, $this->usd($estimate->mechanicsUsd)],
            ['<options=bold>итого на ' . $estimate->terms . ' терминов</>', '', '', '<options=bold>' . $this->usd($estimate->totalUsd) . '</>'],
            ['то же через Batch API (не реализован)', '', '', $this->usd($estimate->totalBatchUsd)],
        ]);

        $this->line("Откуда цифры: {$estimate->source}.");
        $this->line('Это ВЕРХНЯЯ граница: вендор отдаёт часть входных токенов из своего кеша дешевле,');
        $this->line('а одинаковый системный промпт на каждом термине — лучший случай для этого кеша.');
        $this->newLine();
        $this->warn('Сухой прогон: ни одного вызова, ни одной записи. Перед настоящим — снять бэкап (scripts/db-backup.sh).');
    }

    private function runReport(ShowcaseRegenReport $report): void
    {
        $this->table(['метрика', 'значение'], [
            ['терминов со старым паспортом', (string) $report->pending],
            ['взято в работу', (string) $report->attempted],
            ['ядер переписано', (string) $report->regenerated],
            ['упало', $report->failures === [] ? '0' : '<fg=red>' . count($report->failures) . '</>'],
            ['токены in/out', $report->tokensIn . '/' . $report->tokensOut],
            ['<options=bold>потрачено</>', '<options=bold>$' . $report->costUsd . '</>'],
            ['курсор (для --after)', $report->cursor ?? '—'],
        ]);

        foreach ($report->replaced as $row) {
            $this->line("  «{$row['term']}»: «{$row['was']}» → «{$row['now']}»");
        }
        foreach ($report->failures as $row) {
            $this->line("  <fg=red>{$row['stage']}</> {$row['term_id']}: {$row['error']}");
        }

        if ($report->cursor !== null) {
            $this->newLine();
            $this->info("Продолжить: php artisan generation:regenerate-showcase --after={$report->cursor}");
        }
    }

    private function write(ShowcaseRegenReport $report): bool
    {
        $out = $this->stringOption('out');
        if ($out === null) {
            return true;
        }

        $lines = [
            '# Перегенерация витрины — отчёт',
            '',
            "- терминов со старым паспортом: {$report->pending}",
            "- ядер переписано: {$report->regenerated} из {$report->attempted}",
            "- потрачено: \${$report->costUsd} ({$report->tokensIn}/{$report->tokensOut} токенов)",
            '- курсор: ' . ($report->cursor ?? '—'),
            '',
            '## Замены ключа',
            '',
        ];
        foreach ($report->replaced as $row) {
            $lines[] = "- **{$row['term']}** — было `{$row['was']}`, стало `{$row['now']}`";
        }
        if ($report->failures !== []) {
            $lines[] = '';
            $lines[] = '## Падения';
            $lines[] = '';
            foreach ($report->failures as $row) {
                $lines[] = "- `{$row['term_id']}` · {$row['stage']} · {$row['error']}";
            }
        }

        if (file_put_contents($out, implode("\n", $lines) . "\n") === false) {
            $this->error("Could not write the report to {$out}");

            return false;
        }
        $this->info("Отчёт: {$out}");

        return true;
    }

    /** @return list<string> */
    private function versions(): array
    {
        $raw = $this->option('prompt');
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        }

        return $out === [] ? ['legacy', 'v8', 'v9'] : $out;
    }

    private function usd(?string $value): string
    {
        return $value === null ? 'без цены' : '$' . $value;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
