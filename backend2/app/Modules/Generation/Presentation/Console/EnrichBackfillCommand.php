<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentExportGroup;
use App\Modules\Generation\Application\Dto\EnrichmentRunMetrics;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Application\Query\ExportEnrichment;
use App\Modules\Generation\Application\Query\ExportEnrichmentHandler;
use App\Modules\Generation\Application\Query\ListPendingEnrichmentTargets;
use App\Modules\Generation\Application\Query\ListPendingEnrichmentTargetsHandler;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;

/**
 * Runs the enrichment станок over named collections and reports what it cost and what it broke.
 *
 * Deliberately NOT a whole-database backfill: `--collection` is required, because distractors and
 * accepted variants are only as good as the content underneath them, and running the станок over
 * un-proofread content buys a large table of rows nobody can trust. Widening it to everything is a
 * decision to take after reading a run's scrap rate, not a default.
 *
 * Runs inline by default so the metrics land in the terminal. `--queue` hands the same work to
 * Horizon in chunks instead (that is the production path — see EnrichCollectionJob).
 */
final class EnrichBackfillCommand extends Command
{
    /**
     * The generator-version option is `--generator`, NOT `--version`: Symfony Console reserves
     * `--version` globally, and passing it makes artisan print the framework version and exit
     * instead of running this command at all.
     *
     * Also: keep every `{...}` token in the signature on ONE line. Symfony parses them with a regex
     * that does not span newlines, so a wrapped token is silently dropped and its option "does not
     * exist" at runtime.
     */
    /** Inline runs still go term-by-term in chunks, so a Ctrl-C loses at most one chunk's marks. */
    private const CHUNK = 20;

    protected $signature = 'enrich:backfill
        {--collection=* : collection id (ULID); repeatable, REQUIRED — there is no run-everything mode}
        {--generator= : generator version to write and to skip by (default ' . BuildTermEnrichmentsHandler::VERSION . ')}
        {--limit=0 : stop after this many terms (0 = no cap), for a cheap first taste}
        {--fake : use the deterministic fake packer — no network, no spend (wiring smoke test only)}
        {--queue : dispatch chunk jobs instead of running inline}
        {--out= : write the proofreading export (markdown) to this path}';

    protected $description = 'Run the enrichment станок (distractors, accepted variants, ambiguity + language flags) over named collections';

    public function __construct(private readonly DispatchesEnrichment $dispatcher)
    {
        parent::__construct();
    }

    public function handle(
        ListPendingEnrichmentTargetsHandler $pending,
        ExportEnrichmentHandler $export,
    ): int {
        $collectionIds = $this->collectionIds();
        if ($collectionIds === null) {
            return self::FAILURE;
        }

        $version = $this->stringOption('generator') ?? BuildTermEnrichmentsHandler::VERSION;

        if ((bool) $this->option('fake')) {
            config(['services.generation.driver' => 'fake']);
        }

        // Resolved HERE, not method-injected: method injection happens before handle() runs, so an
        // injected handler would already hold the OpenAI packer and `--fake` would silently spend
        // real money. (It did, once, before this line existed.) Everything the driver switch governs
        // has to be resolved after the switch is flipped.
        $build = app(BuildTermEnrichmentsHandler::class);

        $termIds = $pending(new ListPendingEnrichmentTargets($collectionIds, $version));
        $limit = (int) $this->option('limit');
        if ($limit > 0 && count($termIds) > $limit) {
            $this->warn("Capped at {$limit} of " . count($termIds) . ' pending terms (--limit).');
            $termIds = array_slice($termIds, 0, $limit);
        }

        $this->info(sprintf(
            'Enrichment %s · %d collection(s) · %d term(s) pending · driver %s',
            $version,
            count($collectionIds),
            count($termIds),
            (string) config('services.generation.driver'),
        ));

        if ($termIds === []) {
            $this->line('Nothing to do — every term is already marked at this version.');

            return $this->writeExport($export, $collectionIds, $version) ? self::SUCCESS : self::FAILURE;
        }

        if ((bool) $this->option('queue')) {
            $this->dispatcher->enrichTerms($termIds, $version);
            $this->info('Queued ' . count($termIds) . ' term(s) as chunk jobs.');

            return self::SUCCESS;
        }

        $metrics = new EnrichmentRunMetrics();
        $bar = $this->output->createProgressBar(count($termIds));
        $bar->start();
        foreach (array_chunk($termIds, self::CHUNK) as $chunk) {
            $metrics = $metrics->plus($build(new BuildTermEnrichments($chunk, $version)));
            $bar->advance(count($chunk));
        }
        $bar->finish();
        $this->newLine(2);

        $this->report($metrics);

        return $this->writeExport($export, $collectionIds, $version) ? self::SUCCESS : self::FAILURE;
    }

    private function report(EnrichmentRunMetrics $m): void
    {
        $this->table(['метрика', 'значение'], [
            ['термины обработаны', (string) $m->termsSeen],
            ['термины упали (модель/JSON)', $m->termsFailed > 0 ? "<fg=red>{$m->termsFailed}</>" : '0'],
            ['дистракторов предложено', (string) $m->distractorsProposed],
            ['дистракторов записано', (string) $m->distractorsWritten],
            ['<options=bold>% брака дистракторов</>', '<options=bold>' . $m->scrapRatePct() . '%</> (' . $m->distractorsRejected . ')'],
            ['вариантов записано', (string) $m->variantsWritten],
            ['вариантов забраковано (длина)', $m->variantsRejected > 0 ? "<fg=yellow>{$m->variantsRejected}</>" : '0'],
            ['<options=bold>% ambiguous</>', '<options=bold>' . $m->ambiguousRatePct() . '%</> (' . $m->termsAmbiguous . ')'],
            ['<options=bold>% языковых флагов (всего)</>', '<options=bold>' . $m->languageRatePct() . '%</> (' . $m->termsLanguageFlagged . ')'],
            // Split out because the repair differs: leakage → переген, не-слово → правка текста.
            ['  · % misspelled_or_nonword', $m->misspelledRatePct() . '% (' . $m->termsMisspelled . ')'],
            ['  · % ua_leakage', $m->uaLeakageRatePct() . '% (' . $m->termsUaLeakage . ')'],
            ['конфликтов вариант↔дистрактор', (string) $m->termsVariantConflict],
        ]);
    }

    /** @param  list<CollectionId>  $collectionIds */
    private function writeExport(ExportEnrichmentHandler $export, array $collectionIds, string $version): bool
    {
        $out = $this->stringOption('out');
        if ($out === null) {
            return true;
        }

        $groups = $export(new ExportEnrichment($collectionIds, $version));
        if (file_put_contents($out, $this->markdown($groups, $version)) === false) {
            $this->error("Could not write the export to {$out}");

            return false;
        }
        $this->info("Выгрузка на вычитку: {$out}");

        return true;
    }

    /** @param  list<EnrichmentExportGroup>  $groups */
    private function markdown(array $groups, string $version): string
    {
        $lines = [
            '# Выгрузка станка на вычитку',
            '',
            "Версия генератора: `{$version}`.",
            '',
            'Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,',
            'а не дамп базы. Колонка «флаги» — то, что требует решения человека.',
            '',
        ];

        foreach ($groups as $group) {
            $lines[] = "## {$group->title}";
            $lines[] = '';
            if ($group->items === []) {
                $lines[] = '_Нечего вычитывать._';
                $lines[] = '';

                continue;
            }

            foreach ($group->items as $item) {
                $row = $item->row;
                $lines[] = "### {$row->text}";
                $lines[] = '';
                $lines[] = '- **перевод (промпт):** ' . $this->orDash($row->translation);
                $lines[] = '- **эталон-пример:** ' . $this->orDash($row->exampleSentence);

                if ($row->variants !== []) {
                    $lines[] = '- **принимаемые варианты:**';
                    foreach ($row->variants as $variant) {
                        $note = $variant['note'] !== null ? " — _{$variant['note']}_" : '';
                        $lines[] = "    - `{$variant['text']}`{$note}";
                    }
                }

                if ($row->distractors !== []) {
                    $lines[] = '- **дистракторы:**';
                    foreach ($row->distractors as $distractor) {
                        $lines[] = sprintf(
                            '    - `%s` — %s: **%s** → `%s`',
                            $distractor['sentence'],
                            $distractor['error_type'],
                            $distractor['error_span'],
                            $distractor['correction'],
                        );
                    }
                }

                if ($item->findings !== []) {
                    $lines[] = '- **флаги:**';
                    foreach ($item->findings as $finding) {
                        $lines[] = '    - ' . $this->findingLabel($finding->kind) . ' ' . $finding->detail;
                    }
                }

                $lines[] = '';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function findingLabel(FindingKind $kind): string
    {
        // The label says what to DO, not just what was seen — the export is a worklist.
        return match ($kind) {
            FindingKind::Ambiguity => '⚠️ **переформулировать** —',
            FindingKind::Language => '🌐 **не тот язык** —',
            FindingKind::UaLeakage => '🇺🇦 **украинизм → переген** —',
            FindingKind::MisspelledOrNonword => '✍️ **не слово → правка** —',
            FindingKind::VariantConflict => '❗ **конфликт** —',
        };
    }

    /** @return list<CollectionId>|null */
    private function collectionIds(): ?array
    {
        $raw = $this->option('collection');
        $values = is_array($raw) ? $raw : [];

        $ids = [];
        foreach ($values as $value) {
            $id = is_scalar($value) ? (string) $value : '';
            if (! Ulid::isValid($id)) {
                $this->error("Not a valid collection id: {$id}");

                return null;
            }
            $ids[] = CollectionId::fromString($id);
        }

        if ($ids === []) {
            $this->error('At least one --collection is required. There is deliberately no run-everything mode: '
                . 'the станок is only as trustworthy as the content it reads.');

            return null;
        }

        return $ids;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function orDash(?string $value): string
    {
        return $value !== null && trim($value) !== '' ? trim($value) : '—';
    }
}
