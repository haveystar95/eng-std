<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\BackfillEnrichmentSuppressions;
use App\Modules\Generation\Application\Command\BackfillEnrichmentSuppressionsHandler;
use Illuminate\Console\Command;

/**
 * Populates `enrichment_suppressions` from decisions that predate the table, so a топап run against
 * already-cut-down content does not have to re-discover them the hard way.
 *
 * Two sources, one honest gap:
 *
 *  - `database/review/*.json` `remove_distractors` — exact and complete. A `sentence` entry is used
 *    verbatim; a `contains` entry (the reviewer quoted only a fragment) is resolved against the
 *    matching `docs/enrich-v1-*.md` export — the snapshot the review was written against — by finding
 *    the term's section and the one backtick-quoted line containing the fragment.
 *  - a past `enrich:audit-distractors --apply` run — NOT recoverable here. The audit is deterministic
 *    over whatever is CURRENTLY in the table; once it deletes a row, a second pass finds nothing, and
 *    nothing else records what the first pass removed. Going forward this gap is closed (the handler
 *    now writes a suppression at delete time, `source=audit`) — this command only ever backfills what
 *    a review file still has a record of.
 *
 * Idempotent: `suppressDistractor` is `insertOrIgnore` against `(term_id, sentence)`, so re-running
 * this (a new review file added later, a re-read after `migrate:fresh`) never duplicates a row.
 */
final class BackfillEnrichmentSuppressionsCommand extends Command
{
    protected $signature = 'enrich:backfill-suppressions
        {files?* : review files, relative to app root (default: database/review/*.json)}';

    protected $description = 'Бэкфилл enrichment_suppressions из применённых вычиток — идемпотентно';

    public function handle(BackfillEnrichmentSuppressionsHandler $backfill): int
    {
        $files = $this->stringListArgument('files');
        if ($files === []) {
            $files = array_map(
                static fn (string $path): string => basename($path),
                glob(base_path('database/review/*.json')) ?: [],
            );
            sort($files);
            $files = array_map(static fn (string $name): string => "database/review/{$name}", $files);
        }

        $rows = [];
        $unresolved = [];
        foreach ($files as $file) {
            $path = base_path($file);
            if (! is_file($path)) {
                $this->error("Файл вычитки не найден: {$path}");

                return self::FAILURE;
            }

            $review = json_decode((string) file_get_contents($path), true);
            if (! is_array($review)) {
                $this->error("Вычитка не является валидным JSON: {$path}");

                return self::FAILURE;
            }

            $this->line("Читаю {$file}");
            foreach ($this->distractorRows($review) as $entry) {
                $term = (string) ($entry['term'] ?? '');
                if (isset($entry['contains'])) {
                    $sentence = $this->resolveFragment($term, (string) $entry['contains']);
                    if ($sentence === null) {
                        $unresolved[] = "«{$term}» :: contains «{$entry['contains']}» — не нашёл в docs/enrich-v1-*.md";

                        continue;
                    }
                } else {
                    $sentence = (string) ($entry['sentence'] ?? '');
                }

                $rows[] = ['term' => $term, 'sentence' => $sentence, 'source' => 'review'];
            }
        }

        $outcome = $backfill(new BackfillEnrichmentSuppressions($rows));

        $this->table(['действие', 'сделано'], [
            ['новых подавлений записано', (string) $outcome->inserted],
            ['уже были подавлены (пропущено)', (string) $outcome->alreadySuppressed],
        ]);

        if ($unresolved !== []) {
            $this->warn('Не разрешено (contains → предложение), ' . count($unresolved) . ':');
            foreach ($unresolved as $line) {
                $this->line("  · {$line}");
            }
        }
        if ($outcome->unmatched !== []) {
            $this->warn('Не сошлось (' . count($outcome->unmatched) . '):');
            foreach ($outcome->unmatched as $line) {
                $this->line("  · {$line}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $review
     * @return list<array<string, mixed>>
     */
    private function distractorRows(array $review): array
    {
        $rows = $review['remove_distractors'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * A `contains` review entry only quotes a fragment. The full sentence still exists — in the
     * markdown export the review itself was proofread against — under the term's own `### ` heading,
     * as the one backtick-quoted distractor line containing that fragment.
     */
    private function resolveFragment(string $term, string $fragment): ?string
    {
        foreach (glob(base_path('docs/enrich-v1-*.md')) ?: [] as $path) {
            $content = (string) file_get_contents($path);
            $heading = "### {$term}\n";
            $start = mb_strpos($content, $heading);
            if ($start === false) {
                continue;
            }
            $end = mb_strpos($content, "\n### ", $start + mb_strlen($heading));
            $section = $end === false ? mb_substr($content, $start) : mb_substr($content, $start, $end - $start);

            if (preg_match_all('/`([^`]*)`/u', $section, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $candidate) {
                if (str_contains($candidate, $fragment)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** @return list<string> */
    private function stringListArgument(string $name): array
    {
        $value = $this->argument($name);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
