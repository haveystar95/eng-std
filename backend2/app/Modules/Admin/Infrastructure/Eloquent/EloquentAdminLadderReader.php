<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\AdminLadderCounts;
use App\Modules\Admin\Application\Dto\AdminLadderEvent;
use App\Modules\Admin\Application\Dto\AdminLadderFilter;
use App\Modules\Admin\Application\Dto\AdminLadderLearner;
use App\Modules\Admin\Application\Dto\AdminLadderPairFacts;
use App\Modules\Admin\Application\Dto\AdminLadderReview;
use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Port\AdminLadderReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The live-observation projection over Learning's tables (a reporting read — imports no other
 * module's classes, like every other admin reader).
 *
 * Two things here are deliberate and easy to "fix" wrongly later:
 *
 *  - THE LADDER RUNG IS NOT COMPUTED HERE. Only the stored columns come out; the rung is derived one
 *    layer up, from Learning's own function. Adding `CASE WHEN reps >= 6 …` to the SQL would be a
 *    third copy of the ladder, in the one language nobody would think to grep.
 *  - THE GOVERNING REVIEW IS PICKED BY `client_seq`, not by `answered_at`. The device clock is not
 *    the ordering truth anywhere in this system, and a phone whose clock lags would otherwise show
 *    an older answer as the current verdict — a wrong screen at exactly the moment the screen is
 *    being watched to see whether the ladder works.
 *
 * Paging is offset-only, like the review feed: the table is ordered by "most recently answered",
 * which is not a unique key, so the id-keyset walk the other listings use does not apply.
 */
final class EloquentAdminLadderReader implements AdminLadderReader
{
    public function learners(int $limit): array
    {
        $lastReview = DB::table('reviews')->groupBy('user_id')
            ->selectRaw('user_id, MAX(answered_at) AS last_review');
        $lastExposure = DB::table('term_exposures')->groupBy('user_id')
            ->selectRaw('user_id, MAX(shown_at) AS last_exposure');
        $pairs = DB::table('user_term_progress')->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) AS pairs_count');

        // GREATEST ignores NULLs in Postgres, so a learner who has only ever been shown intro cards
        // (no answers yet) still sorts by the thing they did.
        $rows = DB::table('users as u')
            ->leftJoinSub($lastReview, 'r', 'r.user_id', '=', 'u.id')
            ->leftJoinSub($lastExposure, 'e', 'e.user_id', '=', 'u.id')
            ->leftJoinSub($pairs, 'g', 'g.user_id', '=', 'u.id')
            ->selectRaw('u.id, u.name, u.email, GREATEST(r.last_review, e.last_exposure) AS last_activity, COALESCE(g.pairs_count, 0) AS pairs_count')
            ->orderByRaw('GREATEST(r.last_review, e.last_exposure) DESC NULLS LAST')
            ->orderBy('u.id')
            ->limit($limit)
            ->get();

        return array_values(array_map(static fn (stdClass $r): AdminLadderLearner => new AdminLadderLearner(
            id: (string) $r->id,
            name: (string) $r->name,
            email: $r->email !== null ? (string) $r->email : null,
            lastActivityAt: Iso::orNull($r->last_activity),
            pairsCount: (int) $r->pairs_count,
        ), $rows->all()));
    }

    public function pairs(string $userId, AdminLadderFilter $filter, ListWindow $window): Page
    {
        $base = $this->filtered($userId, $filter);

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->join('terms as t', 't.id', '=', 'p.term_id')
            // Most recently answered first: during a session the word just answered rises to the
            // top, which is the whole point of watching this table while a phone is being used.
            // Never-answered pairs sort last; term_id breaks ties so paging is deterministic.
            ->orderByRaw('p.last_reviewed_at DESC NULLS LAST')
            ->orderBy('p.term_id')
            ->offset($window->offset())
            ->limit($window->limit)
            ->get([
                'p.term_id', 'p.state', 'p.acquisition', 'p.learning_step', 'p.reps', 'p.lapses',
                'p.interval_days', 'p.due_at', 'p.last_reviewed_at', 't.text',
            ]);

        /** @var list<string> $termIds */
        $termIds = array_values(array_unique(array_map(
            static fn (stdClass $r): string => (string) $r->term_id,
            $rows->all(),
        )));

        $translations = $this->translations($termIds);
        $collections = $this->collectionsOfTerms($userId, $termIds);
        $exposures = $this->exposures($userId, $termIds);
        $lastReviews = $this->lastReviews($userId, $termIds);

        $items = array_map(static function (stdClass $r) use ($translations, $collections, $exposures, $lastReviews): AdminLadderPairFacts {
            $termId = (string) $r->term_id;

            return new AdminLadderPairFacts(
                termId: $termId,
                text: (string) $r->text,
                translation: $translations[$termId] ?? null,
                collections: $collections[$termId] ?? [],
                state: (string) $r->state,
                acquisition: (string) $r->acquisition,
                learningStep: (int) $r->learning_step,
                reps: (int) $r->reps,
                lapses: (int) $r->lapses,
                intervalDays: (int) $r->interval_days,
                dueAt: Iso::orNull($r->due_at),
                lastReviewedAt: Iso::orNull($r->last_reviewed_at),
                exposedAt: $exposures[$termId] ?? null,
                lastReview: $lastReviews[$termId] ?? null,
            );
        }, $rows->all());

        return new Page(array_values($items), $total, $window->page, $window->limit);
    }

    public function counts(string $userId, ?string $collectionId): AdminLadderCounts
    {
        $base = $this->filtered($userId, new AdminLadderFilter(collectionId: $collectionId));

        $rows = (clone $base)
            ->groupBy('p.state', 'p.acquisition')
            ->selectRaw('p.state, p.acquisition, COUNT(*) AS c')
            ->get();

        $total = 0;
        $byAcquisition = ['new' => 0, 'learning' => 0, 'graduated' => 0];
        $known = 0;

        foreach ($rows->all() as $r) {
            /** @var stdClass $r */
            $c = (int) $r->c;
            $total += $c;

            // `known` wins over the acquisition value the row also carries, so the four buckets
            // partition the pairs instead of double-counting them.
            if ((string) $r->state === 'known') {
                $known += $c;

                continue;
            }
            $acquisition = (string) $r->acquisition;
            if (array_key_exists($acquisition, $byAcquisition)) {
                $byAcquisition[$acquisition] += $c;
            }
        }

        $due = (clone $base)->whereNotNull('p.due_at')->where('p.due_at', '<=', now())->count();

        return new AdminLadderCounts(
            total: $total,
            new: $byAcquisition['new'],
            learning: $byAcquisition['learning'],
            graduated: $byAcquisition['graduated'],
            known: $known,
            due: $due,
        );
    }

    public function events(string $userId, int $limit): array
    {
        // Each log is read in ITS OWN truthful order — reviews by client_seq, intros by the only
        // time they have — and only then merged for display.
        $reviews = DB::table('reviews as r')
            ->leftJoin('terms as t', 't.id', '=', 'r.term_id')
            ->where('r.user_id', $userId)
            ->orderByDesc('r.client_seq')
            ->orderByDesc('r.answered_at')
            ->limit($limit)
            ->get([
                'r.id', 'r.term_id', 'r.exercise_mode', 'r.grade', 'r.is_correct', 'r.is_practice',
                'r.ladder_step', 'r.response', 'r.client_seq', 'r.answered_at', 't.text',
            ]);

        $exposures = DB::table('term_exposures as e')
            ->leftJoin('terms as t', 't.id', '=', 'e.term_id')
            ->where('e.user_id', $userId)
            ->orderByDesc('e.shown_at')
            ->limit($limit)
            ->get(['e.term_id', 'e.shown_at', 't.text']);

        $events = array_map(static fn (stdClass $r): AdminLadderEvent => new AdminLadderEvent(
            id: (string) $r->id,
            kind: AdminLadderEvent::KIND_REVIEW,
            termId: (string) $r->term_id,
            termText: $r->text !== null ? (string) $r->text : null,
            occurredAt: Iso::orNull($r->answered_at),
            exerciseMode: $r->exercise_mode !== null ? (string) $r->exercise_mode : null,
            grade: (string) $r->grade,
            isCorrect: $r->is_correct !== null ? (bool) $r->is_correct : null,
            isPractice: (bool) $r->is_practice,
            ladderStep: $r->ladder_step !== null ? (int) $r->ladder_step : null,
            response: $r->response !== null ? (string) $r->response : null,
            clientSeq: (int) $r->client_seq,
        ), $reviews->all());

        foreach ($exposures->all() as $e) {
            /** @var stdClass $e */
            $events[] = new AdminLadderEvent(
                // The exposure log's key IS the pair (a word is introduced once), so that is its
                // identity here too — there is no event id to borrow.
                id: 'exposure:' . (string) $e->term_id,
                kind: AdminLadderEvent::KIND_EXPOSURE,
                termId: (string) $e->term_id,
                termText: $e->text !== null ? (string) $e->text : null,
                occurredAt: Iso::orNull($e->shown_at),
                exerciseMode: null,
                grade: null,
                isCorrect: null,
                isPractice: false,
                ladderStep: null,
                response: null,
                clientSeq: null,
            );
        }

        usort($events, static function (AdminLadderEvent $a, AdminLadderEvent $b): int {
            $byTime = strcmp($b->occurredAt ?? '', $a->occurredAt ?? '');

            return $byTime !== 0 ? $byTime : ($b->clientSeq ?? 0) <=> ($a->clientSeq ?? 0);
        });

        return array_slice($events, 0, $limit);
    }

    /** The pair query with the screen's filters applied — shared by the table and its counters. */
    private function filtered(string $userId, AdminLadderFilter $filter): Builder
    {
        $q = DB::table('user_term_progress as p')->where('p.user_id', $userId);

        if ($filter->collectionId !== null) {
            $q->whereExists(static function (Builder $sub) use ($filter): void {
                $sub->from('collection_items as ci')
                    ->whereColumn('ci.term_id', 'p.term_id')
                    ->where('ci.collection_id', $filter->collectionId);
            });
        }

        if ($filter->phase === 'known') {
            $q->where('p.state', 'known');
        } elseif ($filter->phase !== null) {
            $q->where('p.acquisition', $filter->phase)->where('p.state', '<>', 'known');
        }

        if ($filter->dueOnly) {
            $q->whereNotNull('p.due_at')->where('p.due_at', '<=', now());
        }

        return $q;
    }

    /**
     * @param  list<string>  $termIds
     * @return array<string, string>
     */
    private function translations(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('term_translations')
            ->whereIn('term_id', $termIds)
            ->orderByDesc('is_primary')
            ->get(['term_id', 'text']);
        foreach ($rows->all() as $r) {
            /** @var stdClass $r */
            $tid = (string) $r->term_id;
            if (! isset($out[$tid])) {
                $out[$tid] = (string) $r->text;   // first seen wins; primary sorts first
            }
        }

        return $out;
    }

    /**
     * Which of the LEARNER'S OWN collections each term came from — owned or added.
     *
     * Not every collection in the catalogue that happens to contain the word: terms are globally
     * deduplicated, so "apple" sits in a dozen system collections the learner has never seen, and
     * listing those would answer a question nobody asked.
     *
     * @param  list<string>  $termIds
     * @return array<string, list<CollectionRefRow>>
     */
    private function collectionsOfTerms(string $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->leftJoin('user_collections as uc', function (JoinClause $join) use ($userId): void {
                $join->on('uc.collection_id', '=', 'c.id')->where('uc.user_id', '=', $userId);
            })
            ->whereIn('ci.term_id', $termIds)
            ->whereNull('c.deleted_at')
            ->where(function (Builder $q) use ($userId): void {
                $q->where('c.owner_id', $userId)->orWhereNotNull('uc.user_id');
            })
            ->orderBy('c.title')
            ->get(['ci.term_id', 'c.id', 'c.title', 'c.type']);

        /** @var array<string, list<CollectionRefRow>> $out */
        $out = [];
        foreach ($rows->all() as $r) {
            /** @var stdClass $r */
            $out[(string) $r->term_id][] = new CollectionRefRow(
                id: (string) $r->id,
                title: (string) $r->title,
                type: (string) $r->type,
            );
        }

        return $out;
    }

    /**
     * @param  list<string>  $termIds
     * @return array<string, string>  ISO instant of the intro, keyed by term id
     */
    private function exposures(string $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        $rows = DB::table('term_exposures')
            ->where('user_id', $userId)
            ->whereIn('term_id', $termIds)
            ->get(['term_id', 'shown_at']);
        foreach ($rows->all() as $r) {
            /** @var stdClass $r */
            $iso = Iso::orNull($r->shown_at);
            if ($iso !== null) {
                $out[(string) $r->term_id] = $iso;
            }
        }

        return $out;
    }

    /**
     * The governing review per pair: DISTINCT ON the term, greatest `client_seq` wins.
     *
     * @param  list<string>  $termIds
     * @return array<string, AdminLadderReview>
     */
    private function lastReviews(string $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('reviews')
            ->select([
                'term_id', 'id', 'exercise_mode', 'grade', 'is_correct', 'is_practice',
                'ladder_step', 'response', 'client_seq', 'answered_at',
            ])
            ->where('user_id', $userId)
            ->whereIn('term_id', $termIds)
            ->orderBy('term_id')
            ->orderByDesc('client_seq')
            ->distinct('term_id')
            ->get();

        $out = [];
        foreach ($rows->all() as $r) {
            /** @var stdClass $r */
            $out[(string) $r->term_id] = new AdminLadderReview(
                id: (string) $r->id,
                exerciseMode: $r->exercise_mode !== null ? (string) $r->exercise_mode : null,
                grade: (string) $r->grade,
                isCorrect: $r->is_correct !== null ? (bool) $r->is_correct : null,
                isPractice: (bool) $r->is_practice,
                ladderStep: $r->ladder_step !== null ? (int) $r->ladder_step : null,
                response: $r->response !== null ? (string) $r->response : null,
                clientSeq: (int) $r->client_seq,
                answeredAt: Iso::orNull($r->answered_at),
            );
        }

        return $out;
    }
}
