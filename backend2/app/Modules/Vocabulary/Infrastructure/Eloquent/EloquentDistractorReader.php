<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\Service\DistractorFamily;
use App\Modules\Shared\Domain\Service\DistractorLength;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentDistractorReader implements DistractorReader
{
    public function __construct(private readonly DistractorLength $length) {}

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
        $targetKind = $target->kind === null ? null : (string) $target->kind;
        $this->appendCandidates($poolIds, $count, $picked, $usedTexts, $usedTranslations, $banned, (string) $target->lang, $family, $targetKind, (string) $target->text);

        // 2. TOP UP FROM THE CATALOGUE AS A WHOLE — every term the app has, in this language, of
        //    this shape, MINUS the words people typed in themselves.
        //
        // The previous rule filtered by the SHELF a term stands on: the learner's own collections,
        // the ones they subscribe to, and the published catalogue. It was written against a real
        // leak (somebody else's private phrase offered as a wrong answer) and it was the wrong
        // instrument for it, in both directions at once. Too narrow, because a plan day's collection
        // is a private folder OWNED BY THE LEARNER — so every plan they had ever run was a legal
        // source of options for the one they were doing, which is half of how «паспорт» came to
        // stand in a lesson about renting a flat. Too wide, because «my shelf» says nothing about
        // whether the text on it is personal.
        //
        // What actually separates a fair filler from somebody's private sentence is not the folder,
        // it is WHO WROTE THE WORD. Generated and curated material is catalogue: it belongs to the
        // app, it is what the app is made of, and a learner meeting an unfamiliar English word as a
        // wrong answer is the trainer working. A term a PERSON typed — into a collection by hand, or
        // through the translator — is theirs, and it never becomes anybody else's option.
        // `terms.source` records exactly that distinction at the moment of import and is the only
        // ownership fact this table has ({@see \App\Modules\Vocabulary\Domain\ValueObject\TermSource}).
        //
        // ONE RULE FOR BOTH SESSIONS. A plan's lesson and the ordinary queue read the same pool: the
        // question «may this word be a wrong answer here» has one answer, and giving it two was how
        // the two paths drifted. What still separates a plan card from an ordinary one is the pool
        // it PREFERS (step 1: the plan's own words, all its days) and how many options it insists on
        // — not who is allowed to fill the gap.
        //
        // Nothing but the TEXT leaves this method, so an option carries no owner, no collection and
        // no trace of where it was found.
        if (count($picked) < $count) {
            $exclude = array_values(array_unique([$targetId->value, ...$poolTermIds]));
            $rows = DB::table('terms')
                ->where('lang', (string) $target->lang)
                ->whereNotIn('id', $exclude)
                // …MINUS what people typed in themselves — «в чужие варианты не попадают». Their
                // OWN hand-saved words are not somebody else's: a learner whose vocabulary is
                // mostly words they entered would otherwise have no options at all on a card of
                // one of them, which is not privacy, it is an empty session. Ownership of a TERM
                // does not exist (they are deduplicated globally); the shelf it stands on is the
                // only thing that does, so «mine» is «on a collection I own».
                ->where(static fn (Builder $q): Builder => $q
                    ->where('source', '!=', TermSource::User->value)
                    ->orWhereExists(static function (Builder $sub) use ($userId): void {
                        $sub->from('collection_items as ci')
                            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
                            ->whereColumn('ci.term_id', 'terms.id')
                            ->where('c.owner_id', $userId->value)
                            ->whereNull('c.deleted_at')
                            ->whereNull('ci.deleted_at');
                    }))
                // THE SHAPE GATE, in SQL. `appendCandidates()` applies the real rule
                // ({@see DistractorFamily}) and would apply it to whatever this limit happened to
                // return — so without the coarse half here, a capped read over the whole table can
                // come back holding nothing of the target's kind and starve a card that had options.
                // The fine half — a question among questions inside `line` — stays in PHP, because
                // it reads the text.
                ->where(static fn (Builder $q): Builder => $targetKind === null
                    ? $q->whereNull('kind')
                    : $q->where('kind', $targetKind))
                ->limit(max($count * 8, 24))
                ->get(['id', 'cefr']);

            // Same level first. Ordered here rather than in SQL: `SELECT DISTINCT` may only order
            // by expressions it selects, and «is this the target's CEFR» is not one of the columns.
            $fallbackIds = array_values(array_map(
                static fn (object $row): string => (string) $row->id,
                $rows->sortBy(static fn (object $row): int => $row->cefr === $target->cefr ? 0 : 1)->values()->all(),
            ));
            $this->appendCandidates($fallbackIds, $count, $picked, $usedTexts, $usedTranslations, $banned, (string) $target->lang, $family, $targetKind, (string) $target->text);
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
     * @param  string|null  $targetKind  the TARGET's kind, for the length band — a `line` is banded
     *         by words and everything else by characters ({@see DistractorLength})
     * @param  string  $targetText  what the band is measured against
     */
    private function appendCandidates(array $candidateIds, int $count, array &$picked, array &$usedTexts, array &$usedTranslations, array $banned, string $lang, string $family, ?string $targetKind, string $targetText): void
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
            // THE LENGTH BAND. Same shape is not enough: `key` offered `accommodation` is answered
            // by picking the short one without reading it. {@see DistractorLength}
            if (! $this->length->fits($targetKind, $targetText, $text)) {
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
