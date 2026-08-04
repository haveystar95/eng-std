<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Dto\AssembledDraft;
use App\Modules\Generation\Application\Dto\AttemptUsage;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Service\DraftValidator;
use App\Modules\Generation\Application\Service\GenerationPipeline;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Console\Command;
use Throwable;

/**
 * Manual quality gauge for AI generation. Runs the eval set (tests/Fixtures/generation-prompts.json)
 * through the real generate → validate path and prints per-prompt metrics: delivered vs requested,
 * phrase / idiom+phrasal ratio, duplicate rate, CEFR spread, image-prompt coverage, tokens & cost.
 *
 * This costs money on the real driver, so it is NOT a CI test — it is a before/after tool. Baseline
 * it on the current prompt version, change the prompt, run again, compare. `--fake` proves the
 * command works without spend but says nothing about quality.
 *
 * @phpstan-type PromptFixture array{id: string, category: string, prompt: string, levels: list<string>, size: int}
 * @phpstan-type EvalReport array<string, mixed>
 */
final class EvalGenerationCommand extends Command
{
    protected $signature = 'generation:eval
        {--fake : use the deterministic fake generator (no network, no spend — smoke test only)}
        {--out= : also write the full report as JSON to this path}
        {--prompt= : prompt version to trial (e.g. v3); defaults to the production PROMPT_VERSION}
        {--source=ru : source (native) language}
        {--target=en : target (learned) language}';

    protected $description = 'Run the generation eval set and report quality metrics (manual, may cost money)';

    public function handle(DraftValidator $validator): int
    {
        $fixtures = $this->loadFixtures();
        if ($fixtures === null) {
            return self::FAILURE;
        }

        if ($this->option('fake')) {
            config(['services.generation.driver' => 'fake']);
        }

        // Trial a prompt version (e.g. v3) without flipping production: override the config the
        // adapter reads to pick its prompt file, BEFORE the generator is resolved.
        $promptVersion = $this->option('prompt') !== null
            ? $this->asString($this->option('prompt'))
            : RequestCollectionGenerationHandler::PROMPT_VERSION;
        config(['services.generation.prompt_version' => $promptVersion]);

        /** @var CollectionGeneratorPort $generator */
        $generator = app(CollectionGeneratorPort::class);

        // Run the SAME overshoot + top-up pipeline production uses, so the eval measures real
        // delivered-vs-requested, not a single raw call. Cost lives on AssembledDraft (summed).
        $pipeline = new GenerationPipeline($generator, $validator);

        $source = new LanguageCode($this->asString($this->option('source')));
        $target = new LanguageCode($this->asString($this->option('target')));
        $imgSupported = property_exists(GeneratedItem::class, 'imageApiPrompt');

        $this->info("Eval set: {$fixtures['count']} prompts · prompt {$promptVersion} · driver "
            . (string) config('services.generation.driver') . " · {$target->value}←{$source->value}");
        if (! $imgSupported) {
            $this->warn('image_api_prompt not in the draft schema yet (pre-v3) — img% shown as «—».');
        }

        $rows = [];
        $reports = [];
        foreach ($fixtures['prompts'] as $p) {
            $brief = new GenerationBrief($p['prompt'], $source, $target, $p['levels'], $p['size']);

            try {
                // No-op onAttempt: the eval measures, it doesn't persist spend.
                $assembled = $pipeline->assemble($brief, static fn (AttemptUsage $u): null => null);
                [$row, $report] = $this->measure($p, $assembled, $imgSupported);
            } catch (Throwable $e) {
                $row = $this->failRow($p, $e);
                $report = ['id' => $p['id'], 'status' => 'error', 'error' => $e->getMessage()];
            }
            $rows[] = $row;
            $reports[] = $report;
        }

        $this->table(
            ['cat', 'id', 'req', 'raw', 'del', 'ph%', 'id+pv%', 'dup', 'cefr', 'img%', 'tok in/out', '$'],
            [...$rows, $this->summaryRow($reports)],
        );

        $out = $this->option('out');
        if (is_string($out) && $out !== '') {
            $payload = [
                'prompt_version' => $promptVersion,
                'driver' => config('services.generation.driver'),
                'source' => $source->value,
                'target' => $target->value,
                'results' => $reports,
                'summary' => $this->summary($reports),
            ];
            file_put_contents($out, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
            $this->info("Report written to {$out}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  PromptFixture  $p
     * @return array{0: list<string>, 1: EvalReport}
     */
    private function measure(array $p, AssembledDraft $a, bool $imgSupported): array
    {
        $clean = $a->draft;
        $delivered = $a->delivered;
        $rawCount = count($a->primaryRaw->items); // primary (overshoot) call, before trim/top-up

        $phraseLike = 0;
        $idiomPhrasal = 0;
        $imgCovered = 0;
        $cefr = [];
        foreach ($clean->items as $item) {
            if (in_array($item->type, ['phrase', 'idiom', 'phrasal_verb'], true)) {
                $phraseLike++;
            }
            if (in_array($item->type, ['idiom', 'phrasal_verb'], true)) {
                $idiomPhrasal++;
            }
            if ($item->cefr !== null) {
                $cefr[$item->cefr] = ($cefr[$item->cefr] ?? 0) + 1;
            }
            if ($imgSupported && $this->hasImagePrompt($item)) {
                $imgCovered++;
            }
        }

        // Duplicates the model produced on the primary call (removed by the validator):
        // raw minus distinct-by-lowercased-text.
        $seen = [];
        foreach ($a->primaryRaw->items as $item) {
            $seen[mb_strtolower(trim($item->text))] = true;
        }
        $dup = $rawCount - count($seen);

        // Total spend across the primary call AND any top-up, formatted like the pre-A2 baseline.
        $cost = $a->costUsd !== null ? number_format((float) $a->costUsd, 4, '.', '') : null;
        $tokensIn = $a->tokensIn;
        $tokensOut = $a->tokensOut;
        $model = $a->model;
        $phrasePct = $delivered > 0 ? (int) round(100 * $phraseLike / $delivered) : 0;
        $idiomPct = $delivered > 0 ? (int) round(100 * $idiomPhrasal / $delivered) : 0;
        $imgPct = $imgSupported ? ($delivered > 0 ? (int) round(100 * $imgCovered / $delivered) : 0) : null;

        $row = [
            $p['category'],
            $p['id'],
            (string) $p['size'],
            (string) $rawCount,
            $delivered < $p['size'] ? "<fg=yellow>{$delivered}</>" : (string) $delivered,
            "{$phrasePct}%",
            "{$idiomPct}%",
            (string) $dup,
            $this->cefrLabel($cefr),
            $imgPct === null ? '—' : "{$imgPct}%",
            "{$tokensIn}/{$tokensOut}",
            $cost === null ? '—' : '$' . $cost,
        ];

        $report = [
            'id' => $p['id'],
            'category' => $p['category'],
            'status' => 'ok',
            'requested' => $p['size'],
            'raw' => $rawCount,
            'delivered' => $delivered,
            'under_delivered' => $delivered < $p['size'],
            'phrase_like_pct' => $phrasePct,
            'idiom_phrasal_pct' => $idiomPct,
            'duplicates_removed' => $dup,
            'cefr' => $cefr,
            'image_prompt_pct' => $imgPct,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd' => $cost,
            'model' => $model,
        ];

        return [$row, $report];
    }

    private function hasImagePrompt(GeneratedItem $item): bool
    {
        // Reflective on purpose: the field doesn't exist on the DTO until v3 adds it, so a static
        // property access wouldn't compile. The property_exists gate at the call site means this
        // only runs once the field is real; here we just read it without the analyzer objecting.
        $ref = new \ReflectionObject($item);
        if (! $ref->hasProperty('imageApiPrompt')) {
            return false;
        }
        $value = $ref->getProperty('imageApiPrompt')->getValue($item);

        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  PromptFixture  $p
     * @return list<string>
     */
    private function failRow(array $p, Throwable $e): array
    {
        return [
            $p['category'],
            $p['id'],
            (string) $p['size'],
            '<fg=red>ERR</>',
            '<fg=red>—</>',
            '—', '—', '—',
            '<fg=red>' . class_basename($e) . '</>',
            '—', '—', '—',
        ];
    }

    /**
     * @param  list<EvalReport>  $reports
     * @return list<string>
     */
    private function summaryRow(array $reports): array
    {
        $s = $this->summary($reports);

        return [
            '<options=bold>TOTAL</>',
            "<options=bold>{$s['ok']}/{$s['total']}</>",
            '', '',
            "<options=bold>{$s['under_delivered']} under</>",
            "<options=bold>{$s['avg_phrase_pct']}%</>",
            "<options=bold>{$s['avg_idiom_pct']}%</>",
            "<options=bold>{$s['total_duplicates']}</>",
            '',
            '',
            '',
            '<options=bold>$' . $s['total_cost'] . '</>',
        ];
    }

    /**
     * @param  list<EvalReport>  $reports
     * @return array{total: int, ok: int, under_delivered: int, avg_phrase_pct: int, avg_idiom_pct: int, total_duplicates: int, total_cost: string}
     */
    private function summary(array $reports): array
    {
        $ok = array_values(array_filter($reports, static fn (array $r): bool => ($r['status'] ?? '') === 'ok'));
        $n = count($ok);
        $sumPhrase = 0;
        $sumIdiom = 0;
        $under = 0;
        $dup = 0;
        $cost = 0.0;
        foreach ($ok as $r) {
            $sumPhrase += is_int($r['phrase_like_pct'] ?? null) ? $r['phrase_like_pct'] : 0;
            $sumIdiom += is_int($r['idiom_phrasal_pct'] ?? null) ? $r['idiom_phrasal_pct'] : 0;
            $under += ($r['under_delivered'] ?? false) === true ? 1 : 0;
            $dup += is_int($r['duplicates_removed'] ?? null) ? $r['duplicates_removed'] : 0;
            $cost += is_string($r['cost_usd'] ?? null) ? (float) $r['cost_usd'] : 0.0;
        }

        return [
            'total' => count($reports),
            'ok' => $n,
            'under_delivered' => $under,
            'avg_phrase_pct' => $n > 0 ? (int) round($sumPhrase / $n) : 0,
            'avg_idiom_pct' => $n > 0 ? (int) round($sumIdiom / $n) : 0,
            'total_duplicates' => $dup,
            'total_cost' => number_format($cost, 4, '.', ''),
        ];
    }

    /** @param array<string, int> $cefr */
    private function cefrLabel(array $cefr): string
    {
        if ($cefr === []) {
            return '—';
        }
        ksort($cefr);
        $parts = [];
        foreach ($cefr as $level => $n) {
            $parts[] = "{$level}:{$n}";
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{count: int, prompts: list<PromptFixture>}|null
     */
    private function loadFixtures(): ?array
    {
        $path = base_path('tests/Fixtures/generation-prompts.json');
        if (! is_file($path)) {
            $this->error("Eval set not found: {$path}");

            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! isset($decoded['prompts']) || ! is_array($decoded['prompts'])) {
            $this->error('Eval set is malformed (expected a "prompts" array).');

            return null;
        }

        $prompts = [];
        foreach ($decoded['prompts'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $levels = [];
            foreach (is_array($row['levels'] ?? null) ? $row['levels'] : [] as $lvl) {
                if (is_string($lvl)) {
                    $levels[] = $lvl;
                }
            }
            $prompts[] = [
                'id' => is_string($row['id'] ?? null) ? $row['id'] : '?',
                'category' => is_string($row['category'] ?? null) ? $row['category'] : '?',
                'prompt' => is_string($row['prompt'] ?? null) ? $row['prompt'] : '',
                'levels' => $levels === [] ? ['A2', 'B1'] : $levels,
                'size' => is_int($row['size'] ?? null) ? $row['size'] : 15,
            ];
        }

        return ['count' => count($prompts), 'prompts' => $prompts];
    }

    private function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
