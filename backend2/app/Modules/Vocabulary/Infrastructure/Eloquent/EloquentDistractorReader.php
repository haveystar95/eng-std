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
        // THE SYNONYM BAN (SYN-1 Ч.2 п. 3). A near-synonym of the term is a SECOND CORRECT ANSWER on
        // its own card: shown «цель» with `purpose` as the key and `goal` among the options, a
        // learner who taps `goal` is right and is marked wrong. The translation-overlap rule below
        // does not catch it — `purpose` and `goal` can easily be glossed «цель» and «задача» and
        // read as two different meanings — so the ban is stated on the synonym table itself, which
        // is the only place that knows the two words are the same answer.
        $banned = $this->synonymBan($targetId->value);

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
        //
        // The pool is filtered by LANGUAGE, one level down in appendCandidates(), for the reason the
        // top-up below has always been: a session's pool is no longer one collection's words, and a
        // pool that mixes pairs is a pool that mixes languages. Shown «привет», the learner was
        // offered `hello`, `hola` and `ciao` — every one of them a correct translation of the prompt,
        // and only the one the answer key names counted (owner's device, 26.08). The language of a
        // term IS the studied side of its pair, so this one comparison is the whole pair gate here:
        // the options are term TEXTS, and a card of pair ru→en may show English and nothing else.
        $poolIds = array_values(array_filter($poolTermIds, static fn (string $id): bool => $id !== $targetId->value));
        $this->appendCandidates($poolIds, $count, $picked, $usedTexts, $usedTranslations, $banned, (string) $target->lang);

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
            $this->appendCandidates($fallbackIds, $count, $picked, $usedTexts, $usedTranslations, $banned, (string) $target->lang);
        }

        return array_slice($picked, 0, $count);
    }

    /**
     * @param  list<string>  $candidateIds
     * @param  list<string>  $picked
     * @param  array<string, true>  $usedTexts
     * @param  array<string, true>  $usedTranslations  every meaning already on the card, prompt first
     * @param  array<string, true>  $banned  normalised texts that are the target's own answer under
     *         another name — its synonyms, and the terms that name IT as one of theirs
     * @param  string  $lang  the card's own language: a candidate written in another one is not a
     *         wrong answer, it is a different card, and the loop below never sees it
     */
    private function appendCandidates(array $candidateIds, int $count, array &$picked, array &$usedTexts, array &$usedTranslations, array $banned, string $lang): void
    {
        if ($candidateIds === [] || count($picked) >= $count) {
            return;
        }

        /** @var array<string, string> $texts */
        $texts = DB::table('terms')->whereIn('id', $candidateIds)->where('lang', $lang)->pluck('text', 'id')->all();
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
            if (isset($banned[$textKey])) {
                continue; // a synonym of the answer IS the answer — see synonymBan()
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
     * Every option text that would secretly be correct for this target, normalised.
     *
     * BOTH DIRECTIONS, because the data is written per term and either side may hold it: the
     * target's own synonyms (`purpose` → `goal`), and the TEXT of any term that lists the target as
     * one of ITS synonyms (`goal` → `purpose`, written while enriching `goal`). One run of the
     * станок over one of the two words is enough to make the pair unusable as options, which is the
     * point — the ban has to work off whatever half of the data exists.
     *
     * @return array<string, true>
     */
    private function synonymBan(string $targetId): array
    {
        $target = DB::table('terms')->where('id', $targetId)->value('text');
        $banned = [];

        foreach (DB::table('term_synonyms')->where('term_id', $targetId)->pluck('text') as $text) {
            $banned[mb_strtolower(trim((string) $text))] = true;
        }

        if (is_string($target)) {
            // The reverse: terms whose synonym list names this one. Matched case-insensitively on
            // the text, the same way the option dedup one level up compares.
            foreach (DB::table('term_synonyms')
                ->join('terms', 'terms.id', '=', 'term_synonyms.term_id')
                ->whereRaw('lower(term_synonyms.text) = ?', [mb_strtolower(trim($target))])
                ->pluck('terms.text') as $text) {
                $banned[mb_strtolower(trim((string) $text))] = true;
            }
        }

        return $banned;
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
