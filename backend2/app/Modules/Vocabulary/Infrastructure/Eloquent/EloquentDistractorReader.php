<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\Service\DistractorFamily;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentDistractorReader implements DistractorReader
{
    public function forTarget(UserId $userId, TermId $targetId, array $poolTermIds, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $target = DB::table('terms')->where('id', $targetId->value)->first(['id', 'lang', 'cefr', 'kind', 'text']);
        if ($target === null) {
            return [];
        }

        $family = DistractorFamily::of(
            $target->kind === null ? null : (string) $target->kind,
            (string) $target->text,
        );

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
        $this->appendCandidates($poolIds, $count, $picked, $usedTexts, $usedTranslations, $banned, (string) $target->lang, $family);

        // 2. Top up from THIS LEARNER'S SHELVES AND THE PUBLIC CATALOGUE — never from anyone else's.
        //
        // This query used to read `terms` whole, filtered by language and nothing more. Terms are
        // deduplicated globally, so a row has no owner and the query looked harmless; what it
        // actually reached was every phrase any user had ever generated, including the 593 English
        // terms sitting in other people's PRIVATE collections. A learner short of pool candidates
        // could be offered somebody else's «Hi, I'm Alex, and I work as a backend developer.» as a
        // wrong answer. Nobody's name was on it and it was still theirs.
        //
        // Ownership of a TERM does not exist here and inventing it would break the dedup invariant.
        // What exists is the shelf it stands on, so that is what is filtered: the learner's own
        // collections and the ones they subscribe to — the same access rule the collections module
        // applies everywhere — plus the published catalogue, which is nobody's in particular and is
        // exactly what a filler option should come from.
        if (count($picked) < $count) {
            $exclude = array_values(array_unique([$targetId->value, ...$poolTermIds]));
            $rows = DB::table('terms as t')
                ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
                ->join('collections as c', 'c.id', '=', 'ci.collection_id')
                ->where('t.lang', (string) $target->lang)
                ->whereNotIn('t.id', $exclude)
                ->where(fn (Builder $q): Builder => $this->readable($q, $userId))
                ->whereNull('c.deleted_at')
                ->whereNull('ci.deleted_at')
                ->distinct()
                ->limit(max($count * 8, 24))
                ->get(['t.id', 't.cefr']);

            // Same level first. Ordered here rather than in SQL: `SELECT DISTINCT` may only order
            // by expressions it selects, and «is this the target's CEFR» is not one of the columns.
            $fallbackIds = array_values(array_map(
                static fn (object $row): string => (string) $row->id,
                $rows->sortBy(static fn (object $row): int => $row->cefr === $target->cefr ? 0 : 1)->values()->all(),
            ));
            $this->appendCandidates($fallbackIds, $count, $picked, $usedTexts, $usedTranslations, $banned, (string) $target->lang, $family);
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
     * @param  string  $family  {@see DistractorFamily} — kind for kind, and inside a spoken turn,
     *         form for form. Nothing outside the family is ever taken; a short card is the answer.
     */
    private function appendCandidates(array $candidateIds, int $count, array &$picked, array &$usedTexts, array &$usedTranslations, array $banned, string $lang, string $family): void
    {
        if ($candidateIds === [] || count($picked) >= $count) {
            return;
        }

        /** @var array<string, array{text: string, kind: string|null}> $rows */
        $rows = [];
        foreach (DB::table('terms')->whereIn('id', $candidateIds)->where('lang', $lang)->get(['id', 'text', 'kind']) as $row) {
            $rows[(string) $row->id] = [
                'text' => (string) $row->text,
                'kind' => $row->kind === null ? null : (string) $row->kind,
            ];
        }
        $translations = $this->translationsByTerm($candidateIds);

        foreach ($candidateIds as $id) {
            if (count($picked) >= $count) {
                return;
            }
            $row = $rows[$id] ?? null;
            if ($row === null) {
                continue;
            }
            $text = $row['text'];
            // THE SHAPE GATE. Asked to recognise «passport», the learner was offered «Hello. Do you
            // have a reservation?» — a whole spoken turn as a wrong answer for one noun. It is not
            // a wrong answer, it is a different kind of question, and it makes the right one
            // obvious by length alone. Since Д-2 the same is true one level finer: a question among
            // statements is answerable without reading a word of it. {@see familyOf()}
            if (DistractorFamily::of($row['kind'], $text) !== $family) {
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
     * The shelves a filler option may be taken off: the learner's own, the ones they subscribe to,
     * and the public catalogue.
     *
     * The first two are the access rule Collections applies everywhere
     * ({@see \App\Modules\Collections\Infrastructure\Eloquent\EloquentUserCollectionTermsReader}).
     * The third is the addition this reader needs and that one does not: a distractor may come from
     * a catalogue collection the learner has never opened, because a published shelf is not
     * anybody's content.
     */
    private function readable(Builder $query, UserId $userId): Builder
    {
        return $query
            ->where('c.owner_id', $userId->value)
            ->orWhere(static fn (Builder $q): Builder => $q
                ->where('c.type', 'system')
                ->where('c.visibility', 'public'))
            ->orWhereExists(static function (Builder $sub) use ($userId): void {
                $sub->from('user_collections as uc')
                    ->whereColumn('uc.collection_id', 'c.id')
                    ->where('uc.user_id', $userId->value)
                    ->whereNull('uc.unsubscribed_at');
            });
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
