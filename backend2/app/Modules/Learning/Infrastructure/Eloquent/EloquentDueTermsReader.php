<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentDueTermsReader implements DueTermsReader
{
    private const TABLE = 'user_term_progress';

    /** @var list<string> */
    private const COLUMNS = ['term_id', 'state', 'interval_days', 'due_at', 'reps', 'acquisition', 'learning_step', 'successful_reviews'];

    public function selectableInPool(UserId $userId, DateTimeImmutable $now, ?array $termIds, int $limit): array
    {
        $query = $this->scoped($userId, $termIds);
        if ($query === null) {
            return [];
        }

        $rows = $query
            ->where(static function (BuilderContract $q) use ($now): void {
                $q->where(static fn (BuilderContract $q) => self::owedInPool($q, $now))
                    // A «знаю» VERIFICATION rides beside the pool, and it is the one card in the app
                    // that is not about a word the learner chose to study. The pair is deliberately
                    // OUT of the pool — the verdict meant the opposite of «учи это» — but the claim
                    // still has to be checked on the day it comes due, or the check that swipe
                    // scheduled would simply never happen. The system auditing a statement, not the
                    // learner's queue.
                    ->orWhere(static fn (BuilderContract $q) => self::dueVerification($q, $now));
            })
            // NULLS FIRST is the whole ordering: it puts the unfinished and the freshly graduated
            // ahead of anything merely due, then soonest first among the rest.
            ->orderByRaw('due_at ASC NULLS FIRST')
            ->orderBy('term_id')
            ->limit($limit)
            ->get(self::COLUMNS);

        return array_values($rows->map($this->toView(...))->all());
    }

    /**
     * A POOL pair the trainer owes a card, for two reasons different in kind: introduced and
     * unfinished (no `due_at`, and none is wanted — the recognition rungs never schedule), or
     * graduated and owed a review (due now, or never scheduled at all: a fresh graduate, or a pair
     * returned from `known`).
     */
    private static function owedInPool(BuilderContract $q, DateTimeImmutable $now): void
    {
        $q->whereNotNull('enrolled_at')
            ->where(static function (BuilderContract $q) use ($now): void {
                $q->where('acquisition', Acquisition::Learning->value)
                    ->orWhere(static function (BuilderContract $q) use ($now): void {
                        $q->where('acquisition', Acquisition::Graduated->value)
                            ->where(static fn (BuilderContract $q) => $q->whereNull('due_at')->orWhere('due_at', '<=', $now));
                    });
            });
    }

    /** A `known` self-assessment whose verification check has come due. */
    private static function dueVerification(BuilderContract $q, DateTimeImmutable $now): void
    {
        $q->where('state', LearningState::Known->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now);
    }

    /**
     * How recent «just enrolled» is. Hours rather than a calendar day because this reader has a
     * clock and no timezone — see the port.
     */
    private const JUST_ENROLLED_HOURS = 24;

    public function introductionsInPool(UserId $userId, DateTimeImmutable $now, ?array $termIds, int $limit): array
    {
        $query = $this->pool($userId, $termIds);
        if ($query === null || $limit <= 0) {
            return [];
        }

        $rows = $query
            ->where('acquisition', Acquisition::New->value)
            // JUST-ENROLLED FIRST, and then first enrolled, first taught. The second half is the
            // queue's own rule; the first is what makes «Учить сразу» visible on a day that is
            // already closed — otherwise the word goes to the back of forty and the act produces
            // nothing the learner can see.
            ->orderByRaw('CASE WHEN enrolled_at >= ? THEN 0 ELSE 1 END', [
                UtcInstant::bind($now->modify('-' . self::JUST_ENROLLED_HOURS . ' hours')),
            ])
            ->orderBy('enrolled_at')
            // `term_id` breaks ties so a repeated call deals the same words rather than a fresh
            // sample of them.
            ->orderBy('term_id')
            ->limit($limit)
            ->get(self::COLUMNS);

        return array_values($rows->map($this->toView(...))->all());
    }

    public function allInPool(UserId $userId, ?array $termIds, int $limit): array
    {
        $query = $this->pool($userId, $termIds);
        if ($query === null) {
            return [];
        }

        // No state/due filter: practice drills whatever the learner is studying. Ordering is
        // irrelevant (the caller shuffles), so we just cap the read.
        $rows = $query->limit($limit)->get(self::COLUMNS);

        return array_values($rows->map($this->toView(...))->all());
    }

    public function allInScope(UserId $userId, array $termIds, int $limit): array
    {
        $query = $this->scoped($userId, $termIds);
        if ($query === null) {
            return [];
        }

        // No enrolment filter and no state/due filter: this is the collection's drill, not the
        // trainer's queue. `enrolled_at` rides along so each row can say which population it is in
        // — the caller deals the two differently, and guessing from `acquisition` would be wrong
        // (a PAUSED word is graduated and still outside the pool).
        $rows = $query->limit($limit)->get([...self::COLUMNS, 'enrolled_at']);

        return array_values($rows->map($this->toScopeView(...))->all());
    }

    /**
     * The pool proper: enrolled pairs only.
     *
     * @param  list<string>|null  $termIds
     */
    private function pool(UserId $userId, ?array $termIds): ?Builder
    {
        return $this->scoped($userId, $termIds)?->whereNotNull('enrolled_at');
    }

    /**
     * One learner's pairs, optionally narrowed to a scope. Returns null for an EMPTY scope — «this
     * collection has no terms» is not the same question as «everything», and `whereIn('term_id', [])`
     * would quietly answer the first with the second's SQL.
     *
     * @param  list<string>|null  $termIds
     */
    private function scoped(UserId $userId, ?array $termIds): ?Builder
    {
        if ($termIds !== null && $termIds === []) {
            return null;
        }

        $query = DB::table(self::TABLE)->where('user_id', $userId->value);

        if ($termIds !== null) {
            $query->whereIn('term_id', $termIds);
        }

        return $query;
    }

    /** A row read by one of the POOL methods: enrolled by construction. */
    private function toView(stdClass $row): DueTermView
    {
        return $this->view($row, inPool: true);
    }

    /** A row read by {@see allInScope()}, where enrolment is the question and not a given. */
    private function toScopeView(stdClass $row): DueTermView
    {
        return $this->view($row, inPool: $row->enrolled_at !== null);
    }

    private function view(stdClass $row, bool $inPool): DueTermView
    {
        return new DueTermView(
            termId: TermId::fromString((string) $row->term_id),
            state: LearningState::from((string) $row->state),
            intervalDays: (int) $row->interval_days,
            dueAt: $row->due_at !== null ? new DateTimeImmutable((string) $row->due_at) : null,
            reps: (int) $row->reps,
            acquisition: Acquisition::from((string) $row->acquisition),
            learningStep: (int) $row->learning_step,
            successfulReviews: (int) $row->successful_reviews,
            inPool: $inPool,
        );
    }
}
