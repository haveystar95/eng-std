<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\AuditTranslations;
use App\Modules\Generation\Application\Command\AuditTranslationsHandler;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Dto\TranslationAuditOutcome;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;

/**
 * The translation QA sweep, by hand, over a chosen set — what used to be bought on every станок call
 * (audit A5) and is now run when somebody is actually going to read it, e.g. before a TestFlight
 * build.
 *
 * It calls a model once per term and writes findings only. No content is touched, so it is safe to
 * re-run; `--dry-run` calls nothing at all and just says how many terms and roughly what it costs.
 */
final class AuditTranslationsCommand extends Command
{
    protected $signature = 'audit:translations
        {--collection=* : collection id (ULID); repeatable}
        {--term=* : term id (ULID); repeatable — a narrower filter than --collection}
        {--limit=0 : stop after this many terms (0 = no cap)}
        {--model= : run the audit on a different model than the configured core one — a second opinion is worth more from a second model}
        {--lang=ru : which translation to audit}
        {--dry-run : count the terms and price the run; calls nothing, writes nothing}
        {--out= : write the disagreements to this markdown file}';

    protected $description = 'Second opinion on stored translations: where an independent rendering disagrees with the database';

    public function handle(AuditTranslationsHandler $audit, GenerationStackConfig $stack): int
    {
        $collectionIds = [];
        foreach ($this->arrayOption('collection') as $value) {
            if (! Ulid::isValid($value)) {
                $this->error("Not a valid collection id: {$value}");

                return self::FAILURE;
            }
            $collectionIds[] = CollectionId::fromString($value);
        }

        $termIds = $this->arrayOption('term');
        if ($collectionIds === [] && $termIds === []) {
            $this->error('At least one --collection or --term is required. There is no audit-everything '
                . 'mode: a sweep nobody reads is spend without a reader.');

            return self::FAILURE;
        }

        $model = $this->stringOption('model') ?? $stack->coreModel;
        $dryRun = (bool) $this->option('dry-run');

        $outcome = $audit(new AuditTranslations(
            collectionIds: $collectionIds,
            termIds: $termIds,
            limit: (int) $this->option('limit'),
            model: $this->stringOption('model'),
            dryRun: $dryRun,
            translationLang: $this->stringOption('lang') ?? 'ru',
        ));

        if ($dryRun) {
            $this->info(sprintf(
                'Аудит переводов (сухой прогон): %d терминов · промпт %s форма enrich · модель %s. Ничего не вызвано и не записано.',
                $outcome->termsSeen,
                $stack->corePromptVersion,
                $model,
            ));

            return self::SUCCESS;
        }

        $this->report($outcome, $model);

        return $this->write($outcome) ? self::SUCCESS : self::FAILURE;
    }

    private function report(TranslationAuditOutcome $outcome, string $model): void
    {
        $this->table(['метрика', 'значение'], [
            ['терминов проверено', (string) $outcome->termsSeen],
            ['модель', $model],
            ['расхождений перевода', $outcome->disagreements === []
                ? '0'
                : '<fg=yellow>' . count($outcome->disagreements) . '</>'],
            ['находок записано', (string) count($outcome->findings)],
            ['вызовов упало', $outcome->failures === [] ? '0' : '<fg=red>' . count($outcome->failures) . '</>'],
            ['токены in/out', ($outcome->tokensIn ?? 0) . '/' . ($outcome->tokensOut ?? 0)],
        ]);

        foreach ($outcome->disagreements as $row) {
            $this->line("  «{$row['term']}» — в базе «{$row['stored']}», прогон дал «{$row['fresh']}»");
        }
    }

    private function write(TranslationAuditOutcome $outcome): bool
    {
        $out = $this->stringOption('out');
        if ($out === null) {
            return true;
        }

        $lines = [
            '# Аудит переводов — расхождения',
            '',
            'Каждая строка: термин, перевод в базе, перевод независимого прогона. Расхождение — это',
            'ВОПРОС к человеку, а не приговор базе: правым может оказаться любой из двух.',
            '',
        ];
        foreach ($outcome->disagreements as $row) {
            $lines[] = "- **{$row['term']}** — в базе `{$row['stored']}` · прогон `{$row['fresh']}`";
        }

        if (file_put_contents($out, implode("\n", $lines) . "\n") === false) {
            $this->error("Could not write the report to {$out}");

            return false;
        }
        $this->info("Отчёт: {$out}");

        return true;
    }

    /** @return list<string> */
    private function arrayOption(string $name): array
    {
        $raw = $this->option($name);
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        }

        return $out;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
