<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\HomeContinueView;
use App\Modules\Learning\Application\Dto\HomeEdgeTermView;
use App\Modules\Learning\Application\Dto\HomeHardTermView;
use App\Modules\Learning\Application\Dto\HomeNextReviewView;
use App\Modules\Learning\Application\Dto\HomePlanView;
use App\Modules\Learning\Application\Dto\HomeTodayView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The home screen's day on the wire.
 *
 * Null is load-bearing here. Every block the design draws has a state in which it is not drawn at
 * all, and the contract says so with `null` / `[]` rather than with a zero — the client's rule is
 * «блок без данных не рисуется», and it can only obey a contract that can express «нет данных».
 *
 * @property-read HomePlanView $resource
 */
final class HomePlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $session = $plan->session;
        $inWork = $plan->inWork;

        return [
            // Which of the four frames this is (17a–17d): plan | done | idle | empty.
            'state' => $plan->state->value,
            'session' => [
                'repeat' => $session->repeat,
                'new' => $session->new,
                'triage' => $session->triage,
                'total' => $session->total,
                'estimated_minutes' => $session->estimatedMinutes,
                'avg_seconds_per_card' => $session->avgSecondsPerCard,
                'triage_collection_id' => $session->triageCollectionId,
                'triage_collection_title' => $session->triageCollectionTitle,
            ],
            'in_work' => [
                'total' => $inWork->total,
                'waiting' => $inWork->waiting,
                'per_day' => $inWork->perDay,
                'new_remaining' => $inWork->newRemaining,
                'days_until_queue' => $inWork->daysUntilQueue,
            ],
            'edge' => array_map(static fn (HomeEdgeTermView $t): array => [
                'term_id' => $t->termId,
                'text' => $t->text,
                'translation' => $t->translation,
                'due_on' => $t->dueOn,
                'in_days' => $t->inDays,
            ], $plan->edge),
            'today' => $this->today($plan->today),
            'next_review' => $this->nextReview($plan->nextReview),
            'hardest' => array_map(static fn (HomeHardTermView $t): array => [
                'term_id' => $t->termId,
                'text' => $t->text,
                'translation' => $t->translation,
                'errors' => $t->errors,
            ], $plan->hardest),
            'continue' => $this->unfinished($plan->unfinished),
            'store' => [
                'count' => $plan->store->count,
                'topics' => $plan->store->topics,
            ],
        ];
    }

    /** @return array{answered: int, seconds: int}|null */
    private function today(?HomeTodayView $today): ?array
    {
        return $today === null ? null : ['answered' => $today->answered, 'seconds' => $today->seconds];
    }

    /** @return array{date: string, count: int}|null */
    private function nextReview(?HomeNextReviewView $next): ?array
    {
        return $next === null ? null : ['date' => $next->date, 'count' => $next->count];
    }

    /** @return array{collection_id: string, title: string, done: int, total: int, remaining: int, abandoned_days: int|null}|null */
    private function unfinished(?HomeContinueView $c): ?array
    {
        return $c === null ? null : [
            'collection_id' => $c->collectionId,
            'title' => $c->title,
            'done' => $c->done,
            'total' => $c->total,
            'remaining' => $c->remaining,
            'abandoned_days' => $c->abandonedDays,
        ];
    }
}
