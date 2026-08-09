<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http;

use App\Modules\Admin\Application\Dto\AdminCollectionProgress;
use App\Modules\Admin\Application\Dto\AdminDayPlanEntry;
use App\Modules\Admin\Application\Dto\AdminDayPlanView;
use App\Modules\Admin\Application\Dto\AdminUserCollectionRow;
use App\Modules\Admin\Application\Dto\AdminUserDetail;
use App\Modules\Admin\Application\Dto\AdminUserRow;
use App\Modules\Admin\Application\Dto\CollectionDetail;
use App\Modules\Admin\Application\Dto\CollectionRow;
use App\Modules\Admin\Application\Dto\CollectionTermRow;
use App\Modules\Admin\Application\Dto\CostBreakdown;
use App\Modules\Admin\Application\Dto\CostCategory;
use App\Modules\Admin\Application\Dto\DashboardView;
use App\Modules\Admin\Application\Dto\DialogDetail;
use App\Modules\Admin\Application\Dto\DialogRow;
use App\Modules\Admin\Application\Dto\GenerationRow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\RequestLogRow;
use App\Modules\Admin\Application\Dto\ReviewRow;
use App\Modules\Admin\Application\Dto\TermDetail;
use App\Modules\Admin\Application\Dto\TermExampleRow;
use App\Modules\Admin\Application\Dto\TermRow;
use App\Modules\Admin\Application\Dto\TermTranslationRow;
use App\Modules\Admin\Application\Dto\UserCollectionWithProgress;
use App\Modules\Admin\Application\Dto\UserCostBreakdown;

/**
 * Maps admin read-DTOs to snake_case JSON arrays. Kept in one place so the shape the panel consumes
 * matches openapi-admin.yaml and controllers stay translation-only.
 */
final class AdminJson
{
    /**
     * @template T
     * @param  Page<T>  $page
     * @param  callable(T): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}}
     */
    public static function page(Page $page, callable $map): array
    {
        return [
            'data' => array_map($map, $page->items),
            'meta' => ['total' => $page->total, 'page' => $page->page, 'per_page' => $page->perPage],
        ];
    }

    /** @return array<string, mixed> */
    public static function dashboard(DashboardView $d): array
    {
        return [
            'totals' => [
                'users' => $d->users,
                'collections' => $d->collections,
                'terms' => $d->terms,
                'reviews_today' => $d->reviewsToday,
                'reviews_7d' => $d->reviews7d,
            ],
            'costs' => [
                'today' => self::cost($d->costToday),
                'last_7d' => self::cost($d->cost7d),
                'all_time' => self::cost($d->costAllTime),
            ],
        ];
    }

    /** @return array<string, float> */
    public static function cost(CostBreakdown $c): array
    {
        return [
            'generation' => $c->generation,
            'practice' => $c->practice,
            'enrichment' => $c->enrichment,
            'example_regen' => $c->exampleRegen,
            'total' => $c->total,
        ];
    }

    /** @return array<string, mixed> */
    public static function userRow(AdminUserRow $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'tier' => $u->tier,
            'cefr' => $u->cefr,
            'created_at' => $u->createdAt,
            'collections_count' => $u->collectionsCount,
            'progress_count' => $u->progressCount,
        ];
    }

    /** @return array<string, mixed> */
    public static function userDetail(AdminUserDetail $u): array
    {
        return [
            'id' => $u->profile->id,
            'name' => $u->profile->name,
            'email' => $u->profile->email,
            'avatar' => $u->profile->avatar,
            'tier' => $u->profile->tier,
            'cefr' => $u->profile->cefr,
            'daily_goal' => $u->profile->dailyGoal,
            'timezone' => $u->profile->timezone,
            'onboarded_at' => $u->profile->onboardedAt,
            'created_at' => $u->profile->createdAt,
            'progress' => [
                'total' => $u->states->total,
                'learning' => $u->states->learning,
                'review' => $u->states->review,
                'relearning' => $u->states->relearning,
                'known' => $u->states->known,
                'learned' => $u->learned,
                'mastered' => $u->mastered,
                'due_today' => $u->dueToday,
            ],
            'reviews_total' => $u->reviewsTotal,
            'reviews_today' => $u->reviewsToday,
            'streak_days' => $u->streakDays,
            'costs' => self::userCost($u->costs),
            'collections' => array_map(self::userCollection(...), $u->collections),
        ];
    }

    /** @return array<string, mixed> */
    public static function userCost(UserCostBreakdown $c): array
    {
        return [
            'generation' => self::costCategory($c->generation),
            'practice' => self::costCategory($c->practice),
            'example_regen' => self::costCategory($c->exampleRegen),
            'total_usd' => $c->totalUsd,
        ];
    }

    /** @return array<string, mixed> */
    public static function costCategory(CostCategory $c): array
    {
        return [
            'tokens_in' => $c->tokensIn,
            'tokens_out' => $c->tokensOut,
            'cost_usd' => $c->costUsd,
            'count' => $c->count,
        ];
    }

    /** @return array<string, mixed> */
    public static function userCollection(AdminUserCollectionRow $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'type' => $c->type,
            'items_count' => $c->itemsCount,
            'added_at' => $c->addedAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function userCollectionWithProgress(UserCollectionWithProgress $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'type' => $c->type,
            'items_count' => $c->itemsCount,
            'added_at' => $c->addedAt,
            'progress' => self::collectionProgress($c->progress),
        ];
    }

    /** @return array<string, int> */
    public static function collectionProgress(AdminCollectionProgress $p): array
    {
        return [
            'total' => $p->total,
            'new' => $p->newCount,
            'in_progress' => $p->inProgress,
            'mastered' => $p->mastered,
            'confirmed' => $p->confirmed,
            'familiar' => $p->familiar,
            'due' => $p->due,
        ];
    }

    /** @return array<string, mixed> */
    public static function review(ReviewRow $r): array
    {
        return [
            'id' => $r->id,
            'term_id' => $r->termId,
            'term_text' => $r->termText,
            'exercise_mode' => $r->exerciseMode,
            'grade' => $r->grade,
            'is_correct' => $r->isCorrect,
            'is_practice' => $r->isPractice,
            'client_seq' => $r->clientSeq,
            'answered_at' => $r->answeredAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function collectionRow(CollectionRow $c): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type,
            'title' => $c->title,
            'owner_id' => $c->ownerId,
            'source' => $c->source,
            'items_count' => $c->itemsCount,
            'created_at' => $c->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function collectionDetail(CollectionDetail $c): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type,
            'title' => $c->title,
            'description' => $c->description,
            'topic' => $c->topic,
            'owner_id' => $c->ownerId,
            'source' => $c->source,
            'source_lang' => $c->sourceLang,
            'target_lang' => $c->targetLang,
            'items_count' => $c->itemsCount,
            'created_at' => $c->createdAt,
            'terms' => array_map(self::collectionTerm(...), $c->terms),
        ];
    }

    /** @return array<string, mixed> */
    public static function collectionTerm(CollectionTermRow $t): array
    {
        return [
            'term_id' => $t->termId,
            'text' => $t->text,
            'translation' => $t->translation,
            'position' => $t->position,
        ];
    }

    /** @return array<string, mixed> */
    public static function termRow(TermRow $t): array
    {
        return [
            'id' => $t->id,
            'lang' => $t->lang,
            'text' => $t->text,
            'type' => $t->type,
            'translation' => $t->translation,
            'created_at' => $t->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function termDetail(TermDetail $t): array
    {
        return [
            'id' => $t->id,
            'lang' => $t->lang,
            'text' => $t->text,
            'normalized_text' => $t->normalizedText,
            'type' => $t->type,
            'pos' => $t->pos,
            'ipa' => $t->ipa,
            'audio_url' => $t->audioUrl,
            'source' => $t->source,
            'created_at' => $t->createdAt,
            'translations' => array_map(static fn (TermTranslationRow $r): array => [
                'lang' => $r->lang, 'text' => $r->text, 'is_primary' => $r->isPrimary,
            ], $t->translations),
            'examples' => array_map(static fn (TermExampleRow $r): array => [
                'sentence' => $r->sentence, 'translation' => $r->translation,
            ], $t->examples),
            'collections' => array_map(static fn ($r): array => [
                'id' => $r->id, 'title' => $r->title, 'type' => $r->type,
            ], $t->collections),
            'progress_count' => $t->progressCount,
        ];
    }

    /** @return array<string, mixed> */
    public static function requestLog(RequestLogRow $r): array
    {
        return [
            'id' => $r->id,
            'direction' => $r->direction,
            'method' => $r->method,
            'host' => $r->host,
            'path' => $r->path,
            'service' => $r->service,
            'status' => $r->status,
            'duration_ms' => $r->durationMs,
            'user_id' => $r->userId,
            'occurred_at' => $r->occurredAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function dialogRow(DialogRow $d): array
    {
        return [
            'id' => $d->id,
            'user_id' => $d->userId,
            'collection_id' => $d->collectionId,
            'status' => $d->status,
            'tokens_in' => $d->tokensIn,
            'tokens_out' => $d->tokensOut,
            'cost_usd' => $d->costUsd,
            'created_at' => $d->createdAt,
            'finished_at' => $d->finishedAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function dialogDetail(DialogDetail $d): array
    {
        return [
            'id' => $d->id,
            'user_id' => $d->userId,
            'collection_id' => $d->collectionId,
            'status' => $d->status,
            'tokens_in' => $d->tokensIn,
            'tokens_out' => $d->tokensOut,
            'cost_usd' => $d->costUsd,
            'summary' => $d->summary,
            'created_at' => $d->createdAt,
            'finished_at' => $d->finishedAt,
            'transcript' => array_map(static fn ($l): array => [
                'role' => $l->role, 'text' => $l->text, 'ts' => $l->ts,
            ], $d->transcript),
        ];
    }

    /** @return array<string, mixed> */
    public static function generation(GenerationRow $g): array
    {
        return [
            'id' => $g->id,
            'user_id' => $g->userId,
            'prompt' => $g->prompt,
            'status' => $g->status,
            'model' => $g->model,
            'tokens_in' => $g->tokensIn,
            'tokens_out' => $g->tokensOut,
            'cost_usd' => $g->costUsd,
            'collection_id' => $g->collectionId,
            'error' => $g->error,
            'created_at' => $g->createdAt,
            'finished_at' => $g->finishedAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function dayPlan(AdminDayPlanView $p): array
    {
        return [
            'date' => $p->date,
            'timezone' => $p->timezone,
            'due_count' => $p->dueCount,
            'new_introduced' => $p->newIntroduced,
            'new_terms_per_day' => $p->newTermsPerDay,
            'entries' => array_map(self::dayPlanEntry(...), $p->entries),
        ];
    }

    /** @return array<string, mixed> */
    public static function dayPlanEntry(AdminDayPlanEntry $e): array
    {
        return [
            'term_id' => $e->termId,
            'text' => $e->text,
            'translation' => $e->translation,
            'type' => $e->type,
            'state' => $e->state,
            'reps' => $e->reps,
            'interval_days' => $e->intervalDays,
            'due_at' => $e->dueAt,
            'exercise_mode' => $e->exerciseMode,
            'clozeable' => $e->clozeable,
            'is_new' => $e->isNew,
        ];
    }
}
