<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\ApplyEnrichmentReview;
use App\Modules\Generation\Application\Command\ApplyEnrichmentReviewHandler;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use Illuminate\Console\Command;

/**
 * Applies a committed review file to the enrichment content. Re-runnable: the removals are no-ops once
 * gone, the additions are deduped by the unique index, and the acknowledgements are insert-or-ignore —
 * so this survives the `migrate:fresh` the database gets before the app cuts over.
 */
final class ApplyEnrichmentReviewCommand extends Command
{
    protected $signature = 'enrich:apply-review
        {file=database/review/enrich-v1-store5.json : review file, relative to the app root}
        {--generator= : generator version the review belongs to (default: the file, else the current)}';

    protected $description = 'Apply a proofreader review of an enrichment run (removals, additions, translation fixes)';

    public function handle(ApplyEnrichmentReviewHandler $apply): int
    {
        $path = base_path($this->stringArg('file'));
        if (! is_file($path)) {
            $this->error("Review file not found: {$path}");

            return self::FAILURE;
        }

        $review = json_decode((string) file_get_contents($path), true);
        if (! is_array($review)) {
            $this->error('Review file is not valid JSON.');

            return self::FAILURE;
        }

        $version = $this->stringOption('generator')
            ?? (is_string($review['generator_version'] ?? null) ? $review['generator_version'] : BuildTermEnrichmentsHandler::VERSION);

        $this->info("Применяю вычитку · {$path} · версия {$version}");

        $outcome = $apply(new ApplyEnrichmentReview($review, $version));

        $this->table(['действие', 'сделано'], [
            ['дистракторов удалено', (string) $outcome->distractorsRemoved],
            ['дистракторов исправлено (span/correction)', (string) $outcome->distractorsFixed],
            ['вариантов удалено', (string) $outcome->variantsRemoved],
            ['заметок варианта исправлено', (string) $outcome->variantNotesFixed],
            ['вариантов добавлено', (string) $outcome->variantsAdded],
            ['вариантов отклонено валидатором', $outcome->variantsRejected > 0
                ? "<fg=yellow>{$outcome->variantsRejected}</>"
                : '0'],
            ['переводов исправлено', (string) $outcome->translationsFixed],
            ['переводов примера исправлено', (string) $outcome->exampleTranslationsFixed],
            ['находок погашено', (string) $outcome->findingsAcknowledged],
        ]);

        if ($outcome->unmatched !== []) {
            // Loud on purpose: a review entry that matched nothing is either a typo in the review or
            // content that moved under it. Counting it as success is how a review quietly stops working.
            $this->warn('Не сошлось (' . count($outcome->unmatched) . '):');
            foreach ($outcome->unmatched as $line) {
                $this->line("  · {$line}");
            }
        }

        return self::SUCCESS;
    }

    private function stringArg(string $name): string
    {
        $value = $this->argument($name);

        return is_scalar($value) ? (string) $value : '';
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
