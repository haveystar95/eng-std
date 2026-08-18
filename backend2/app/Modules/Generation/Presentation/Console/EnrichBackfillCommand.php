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
use App\Modules\Generation\Application\Query\GetCollectionTranslationLang;
use App\Modules\Generation\Application\Query\GetCollectionTranslationLangHandler;
use App\Modules\Generation\Application\Query\ListPendingEnrichmentTargets;
use App\Modules\Generation\Application\Query\ListPendingEnrichmentTargetsHandler;
use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Infrastructure\Support\ExportHeader;
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
        {--topup= : top-up mode — take terms whose pinned example has FEWER than N distractors, ignoring the version mark}
        {--fake : use the deterministic fake packer — no network, no spend (wiring smoke test only)}
        {--queue : dispatch chunk jobs instead of running inline}
        {--out= : write the proofreading export (markdown) to this path}
        {--include-ambiguity : keep kind=ambiguity ("переформулировать") findings in the report and the export; store5 showed these are mostly back-translation trivia, so they are written to the journal as always but left out of both by default}';

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
        $includeAmbiguity = (bool) $this->option('include-ambiguity');

        if ((bool) $this->option('fake')) {
            config(['services.generation.driver' => 'fake']);
        }

        // Resolved HERE, not method-injected: method injection happens before handle() runs, so an
        // injected handler would already hold the OpenAI packer and `--fake` would silently spend
        // real money. (It did, once, before this line existed.) Everything the driver switch governs
        // has to be resolved after the switch is flipped.
        $build = app(BuildTermEnrichmentsHandler::class);

        // Top-up asks about coverage, not about runs: a proofreader's deletions leave terms both
        // "already processed" and short of options, and the version mark hides exactly those.
        $topUp = $this->stringOption('topup');
        $termIds = $pending(new ListPendingEnrichmentTargets(
            $collectionIds,
            $version,
            $topUp !== null ? max(1, (int) $topUp) : null,
        ));
        // Every named collection teaches from the same language in practice; when they disagree, the
        // first one wins and the run says so rather than picking silently.
        $lang = app(GetCollectionTranslationLangHandler::class)(new GetCollectionTranslationLang($collectionIds));

        $limit = (int) $this->option('limit');
        if ($limit > 0 && count($termIds) > $limit) {
            $this->warn("Capped at {$limit} of " . count($termIds) . ' pending terms (--limit).');
            $termIds = array_slice($termIds, 0, $limit);
        }

        $this->info(sprintf(
            'Enrichment %s · %d collection(s) · %d term(s) %s · driver %s',
            $version,
            count($collectionIds),
            count($termIds),
            $topUp !== null ? "под догон (<{$topUp} дистракторов)" : 'pending',
            (string) config('services.generation.driver'),
        ));
        $this->line("Язык переводов: «{$lang}» (из source_lang коллекции).");

        if ($termIds === []) {
            $this->line('Nothing to do — every term is already marked at this version.');

            return $this->writeExport($export, $collectionIds, $version, $includeAmbiguity) ? self::SUCCESS : self::FAILURE;
        }

        if ((bool) $this->option('queue')) {
            $this->dispatcher->enrichTerms($termIds, $version, $lang);
            $this->info('Queued ' . count($termIds) . ' term(s) as chunk jobs.');

            return self::SUCCESS;
        }

        $metrics = new EnrichmentRunMetrics();
        $bar = $this->output->createProgressBar(count($termIds));
        $bar->start();
        foreach (array_chunk($termIds, self::CHUNK) as $chunk) {
            $metrics = $metrics->plus($build(new BuildTermEnrichments($chunk, $version, $lang)));
            $bar->advance(count($chunk));
        }
        $bar->finish();
        $this->newLine(2);

        $this->report($metrics, $includeAmbiguity);

        return $this->writeExport($export, $collectionIds, $version, $includeAmbiguity) ? self::SUCCESS : self::FAILURE;
    }

    private function report(EnrichmentRunMetrics $m, bool $includeAmbiguity): void
    {
        $rows = [
            ['термины обработаны', (string) $m->termsSeen],
            ['термины упали (модель/JSON)', $m->termsFailed > 0 ? "<fg=red>{$m->termsFailed}</>" : '0'],
            ['дистракторов предложено', (string) $m->distractorsProposed],
            ['дистракторов записано', (string) $m->distractorsWritten],
            ['<options=bold>% брака дистракторов</>', '<options=bold>' . $m->scrapRatePct() . '%</> (' . $m->distractorsRejected . ')'],
            ['дистракторов на пример (валидных)', (string) $m->distractorsPerExample()],
            // pick_correct needs 1 correct + 2 wrong: these examples cannot host it at all.
            ['примеров с <2 дистракторами', $m->examplesUnderTwoDistractors > 0
                ? "<fg=yellow>{$m->examplesUnderTwoDistractors}</> ({$m->underTwoDistractorsPct()}%)"
                : '0'],
            ['вариантов записано', (string) $m->variantsWritten],
            ['вариантов забраковано (длина)', $m->variantsRejected > 0 ? "<fg=yellow>{$m->variantsRejected}</>" : '0'],
        ];
        // Demoted by default (store5: 31 of 41 flags were back-translation trivia, none useful) — the
        // finding is still written to the journal by BuildTermEnrichmentsHandler either way, only the
        // headline rate is noisy enough to hide from a routine run's report.
        if ($includeAmbiguity) {
            $rows[] = ['<options=bold>% ambiguous</>', '<options=bold>' . $m->ambiguousRatePct() . '%</> (' . $m->termsAmbiguous . ')'];
        }
        $rows[] = ['<options=bold>% языковых флагов (всего)</>', '<options=bold>' . $m->languageRatePct() . '%</> (' . $m->termsLanguageFlagged . ')'];
        // Split out because the repair differs: leakage → переген, не-слово → правка текста.
        $rows[] = ['  · % misspelled_or_nonword', $m->misspelledRatePct() . '% (' . $m->termsMisspelled . ')'];
        $rows[] = ['  · % ua_leakage', $m->uaLeakageRatePct() . '% (' . $m->termsUaLeakage . ')'];
        $rows[] = ['конфликтов вариант↔дистрактор', (string) $m->termsVariantConflict];

        $this->table(['метрика', 'значение'], $rows);
    }

    /** @param  list<CollectionId>  $collectionIds */
    private function writeExport(ExportEnrichmentHandler $export, array $collectionIds, string $version, bool $includeAmbiguity): bool
    {
        $out = $this->stringOption('out');
        if ($out === null) {
            return true;
        }

        $groups = $export(new ExportEnrichment($collectionIds, $version));
        if (file_put_contents($out, $this->markdown($groups, $version, $includeAmbiguity)) === false) {
            $this->error("Could not write the export to {$out}");

            return false;
        }
        $this->info("Выгрузка на вычитку: {$out}");

        return true;
    }

    /** @param  list<EnrichmentExportGroup>  $groups */
    private function markdown(array $groups, string $version, bool $includeAmbiguity): string
    {
        // The header rule lives in ONE place now ({@see ExportHeader}) — a second exporting command
        // must not have to re-type it to be correct, which is exactly how a rule drifts.
        $header = ExportHeader::now();

        $lines = [
            $header->comment(),
            '# Выгрузка станка на вычитку',
            '',
            $header->line("версия генератора: `{$version}`"),
            '',
            'Снимок старше правок в базе — выгрузку надо снять заново: то, что здесь написано, было',
            'верно на момент снимка и с тех пор могло быть починено.',
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
                // Filtered at display time, not in ExportEnrichmentHandler: whether ambiguity is
                // noise is a proofreading-worklist concern, not a fact about what the run wrote.
                $findings = $includeAmbiguity
                    ? $item->findings
                    : array_values(array_filter($item->findings, static fn (EnrichmentFinding $f): bool => $f->kind !== FindingKind::Ambiguity));

                if ($row->variants === [] && $row->distractors === [] && $findings === []) {
                    continue;
                }

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

                if ($findings !== []) {
                    $lines[] = '- **флаги:**';
                    foreach ($findings as $finding) {
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
