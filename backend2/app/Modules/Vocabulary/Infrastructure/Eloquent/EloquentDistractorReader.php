<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Illuminate\Support\Facades\DB;

final class EloquentDistractorReader implements DistractorReader
{
    public function forTarget(TermId $targetId, array $poolTermIds, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $target = DB::table('terms')->where('id', $targetId->value)->first(['id', 'lang', 'cefr']);
        if ($target === null) {
            return [];
        }

        $targetTranslations = $this->translationsByTerm([$targetId->value])[$targetId->value] ?? [];

        /** @var list<string> $picked */
        $picked = [];
        /** @var array<string, true> $usedTexts */
        $usedTexts = [];
        // The MEANINGS already on the card, starting with the prompt's own. Two options that read
        // the same in the learner's language are one option: «check-in desk» and «front desk» are
        // both «стойка регистрации», so whichever is the answer, the other is equally right and the
        // card has a second correct answer on it (QA-17). Seeded with the target's translations so
        // this is one rule instead of two — the prompt is just the first meaning taken.
        $usedTranslations = $targetTranslations;

        // 1. Prefer the session's pool (its collection), minus the target itself.
        $poolIds = array_values(array_filter($poolTermIds, static fn (string $id): bool => $id !== $targetId->value));
        $this->appendCandidates($poolIds, $count, $picked, $usedTexts, $usedTranslations);

        // 2. Top up from same-language terms of a similar level (same cefr first).
        if (count($picked) < $count) {
            $exclude = array_values(array_unique([$targetId->value, ...$poolTermIds]));
            $fallbackIds = array_values(DB::table('terms')
                ->where('lang', (string) $target->lang)
                ->whereNotIn('id', $exclude)
                ->orderByRaw('(cefr IS DISTINCT FROM ?)', [$target->cefr])
                ->limit(max($count * 4, 8))
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all());
            $this->appendCandidates($fallbackIds, $count, $picked, $usedTexts, $usedTranslations);
        }

        return array_slice($picked, 0, $count);
    }

    /**
     * @param  list<string>  $candidateIds
     * @param  list<string>  $picked
     * @param  array<string, true>  $usedTexts
     * @param  array<string, true>  $usedTranslations  every meaning already on the card, prompt first
     */
    private function appendCandidates(array $candidateIds, int $count, array &$picked, array &$usedTexts, array &$usedTranslations): void
    {
        if ($candidateIds === [] || count($picked) >= $count) {
            return;
        }

        /** @var array<string, string> $texts */
        $texts = DB::table('terms')->whereIn('id', $candidateIds)->pluck('text', 'id')->all();
        $translations = $this->translationsByTerm($candidateIds);

        foreach ($candidateIds as $id) {
            if (count($picked) >= $count) {
                return;
            }
            $text = $texts[$id] ?? null;
            if ($text === null) {
                continue;
            }
            $textKey = mb_strtolower(trim($text));
            if (isset($usedTexts[$textKey])) {
                continue; // no duplicate option texts
            }
            // Exclude near-duplicates by MEANING, against the prompt AND against every option
            // already taken — a translation twin reads as correct for the same prompt whichever of
            // the two the card happens to be asking about.
            $candidateTranslations = $translations[$id] ?? [];
            if ($this->overlaps($candidateTranslations, $usedTranslations)) {
                continue;
            }
            $picked[] = $text;
            $usedTexts[$textKey] = true;
            foreach ($candidateTranslations as $key => $_) {
                $usedTranslations[$key] = true;
            }
        }
    }

    /**
     * @param  array<string, true>  $a
     * @param  array<string, true>  $b
     */
    private function overlaps(array $a, array $b): bool
    {
        foreach ($a as $key => $_) {
            if (isset($b[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $termIds
     * @return array<string, array<string, true>>  term id → set of normalized translation texts
     */
    private function translationsByTerm(array $termIds): array
    {
        $out = [];
        foreach (DB::table('term_translations')->whereIn('term_id', $termIds)->get(['term_id', 'text']) as $row) {
            $out[(string) $row->term_id][mb_strtolower(trim((string) $row->text))] = true;
        }

        return $out;
    }
}
