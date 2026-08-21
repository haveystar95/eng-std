<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http;

use App\Modules\Admin\Application\Dto\AdminCollectionProgress;
use App\Modules\Admin\Application\Dto\AdminDayPlanEntry;
use App\Modules\Admin\Application\Dto\AdminDayPlanView;
use App\Modules\Admin\Application\Dto\AdminLadderCounts;
use App\Modules\Admin\Application\Dto\AdminLadderEvent;
use App\Modules\Admin\Application\Dto\AdminLadderLearner;
use App\Modules\Admin\Application\Dto\AdminLadderPair;
use App\Modules\Admin\Application\Dto\AdminLadderProgressView;
use App\Modules\Admin\Application\Dto\AdminLadderReview;
use App\Modules\Admin\Application\Dto\AdminUserCollectionRow;
use App\Modules\Admin\Application\Dto\AdminUserDetail;
use App\Modules\Admin\Application\Dto\AdminUserRow;
use App\Modules\Admin\Application\Dto\CollectionContentHealth;
use App\Modules\Admin\Application\Dto\CollectionDetail;
use App\Modules\Admin\Application\Dto\CollectionRefRow;
use App\Modules\Admin\Application\Dto\CollectionRow;
use App\Modules\Admin\Application\Dto\CollectionTermRow;
use App\Modules\Admin\Application\Dto\ContentHealthCollectionRow;
use App\Modules\Admin\Application\Dto\ContentHealthScope;
use App\Modules\Admin\Application\Dto\ContentHealthSummary;
use App\Modules\Admin\Application\Dto\ContentHealthTermRow;
use App\Modules\Admin\Application\Dto\ContentLabelCount;
use App\Modules\Admin\Application\Dto\ContentVersionCount;
use App\Modules\Admin\Application\Dto\CostByPurposeView;
use App\Modules\Admin\Application\Dto\CostBreakdown;
use App\Modules\Admin\Application\Dto\CostCategory;
use App\Modules\Admin\Application\Dto\DashboardView;
use App\Modules\Admin\Application\Dto\EnrichmentFindingRow;
use App\Modules\Admin\Application\Dto\ExampleDistractorRow;
use App\Modules\Admin\Application\Dto\DialogDetail;
use App\Modules\Admin\Application\Dto\DialogRow;
use App\Modules\Admin\Application\Dto\GenerationRow;
use App\Modules\Admin\Application\Dto\ModeSimulationRow;
use App\Modules\Admin\Application\Dto\PassportDistractorRow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\PlaygroundProviderRow;
use App\Modules\Admin\Application\Dto\PlaygroundResult;
use App\Modules\Admin\Application\Dto\PlaygroundValidation;
use App\Modules\Admin\Application\Dto\PlaygroundValidationRow;
use App\Modules\Admin\Application\Dto\PurposeCost;
use App\Modules\Admin\Application\Dto\RequestLogDetail;
use App\Modules\Admin\Application\Dto\RequestLogRow;
use App\Modules\Admin\Application\Dto\ReviewRow;
use App\Modules\Admin\Application\Dto\TermContentPassport;
use App\Modules\Admin\Application\Dto\TermDetail;
use App\Modules\Admin\Application\Dto\TermExampleRow;
use App\Modules\Admin\Application\Dto\TermRow;
use App\Modules\Admin\Application\Dto\TermTranslationRow;
use App\Modules\Admin\Application\Dto\TermVariantRow;
use App\Modules\Admin\Application\Dto\UserCollectionWithProgress;
use App\Modules\Collections\Application\Dto\CollectionImpact;
use App\Modules\Vocabulary\Application\Dto\TermImpact;
use App\Modules\Admin\Application\Dto\UserCostBreakdown;

/**
 * Maps admin read-DTOs to snake_case JSON arrays. Kept in one place so the shape the panel consumes
 * matches openapi-admin.yaml and controllers stay translation-only.
 */
final class AdminJson
{
    /**
     * `meta.next_cursor` is null on the last page (and always null in offset mode), which is the
     * infinite scroll's stop condition — it never has to compare a running count against a total.
     *
     * @template T
     * @param  Page<T>  $page
     * @param  callable(T): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int, next_cursor: string|null}}
     */
    public static function page(Page $page, callable $map): array
    {
        return [
            'data' => array_map($map, $page->items),
            'meta' => [
                'total' => $page->total,
                'page' => $page->page,
                'per_page' => $page->perPage,
                'next_cursor' => $page->nextCursor,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function dashboard(DashboardView $d): array
    {
        return [
            'totals' => [
                'users' => $d->users,
                'active_users_7d' => $d->activeUsers7d,
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
            'recent_failures' => array_map(self::requestLog(...), $d->recentFailures),
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
    public static function ladderLearner(AdminLadderLearner $l): array
    {
        return [
            'id' => $l->id,
            'name' => $l->name,
            'email' => $l->email,
            'last_activity_at' => $l->lastActivityAt,
            'pairs_count' => $l->pairsCount,
        ];
    }

    /**
     * The pairs page carries its counters, so the table and the header above it always describe the
     * same moment even though the screen re-reads every few seconds.
     *
     * @return array<string, mixed>
     */
    public static function ladderProgress(AdminLadderProgressView $v): array
    {
        return self::page($v->page, self::ladderPair(...)) + ['counts' => self::ladderCounts($v->counts)];
    }

    /** @return array<string, int> */
    public static function ladderCounts(AdminLadderCounts $c): array
    {
        return [
            'total' => $c->total,
            'new' => $c->new,
            'learning' => $c->learning,
            'graduated' => $c->graduated,
            'known' => $c->known,
            'due' => $c->due,
            'out_of_pool' => $c->outOfPool,
        ];
    }

    /** @return array<string, mixed> */
    public static function ladderPair(AdminLadderPair $p): array
    {
        $f = $p->facts;

        return [
            'term_id' => $f->termId,
            'text' => $f->text,
            'translation' => $f->translation,
            'collections' => array_map(static fn (CollectionRefRow $c): array => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->type,
            ], $f->collections),
            'state' => $f->state,
            'acquisition' => $f->acquisition,
            'learning_step' => $f->learningStep,
            // null = outside the ladder (a triage «знаю»). Never conflate it with step 0.
            'ladder_step' => $p->step,
            'reps' => $f->reps,
            'lapses' => $f->lapses,
            'interval_days' => $f->intervalDays,
            'due_at' => $f->dueAt,
            'last_reviewed_at' => $f->lastReviewedAt,
            'exposed_at' => $f->exposedAt,
            'enrolled_at' => $f->enrolledAt,
            'last_review' => $f->lastReview !== null ? self::ladderReview($f->lastReview) : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function ladderReview(AdminLadderReview $r): array
    {
        return [
            'id' => $r->id,
            'exercise_mode' => $r->exerciseMode,
            'grade' => $r->grade,
            'is_correct' => $r->isCorrect,
            'is_practice' => $r->isPractice,
            'ladder_step' => $r->ladderStep,
            'response' => $r->response,
            'client_seq' => $r->clientSeq,
            'answered_at' => $r->answeredAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function ladderEvent(AdminLadderEvent $e): array
    {
        return [
            'id' => $e->id,
            'kind' => $e->kind,
            'term_id' => $e->termId,
            'term_text' => $e->termText,
            'occurred_at' => $e->occurredAt,
            'exercise_mode' => $e->exerciseMode,
            'grade' => $e->grade,
            'is_correct' => $e->isCorrect,
            'is_practice' => $e->isPractice,
            'ladder_step' => $e->ladderStep,
            'response' => $e->response,
            'client_seq' => $e->clientSeq,
            'verdict' => $e->verdict,
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
            'owner_email' => $c->ownerEmail,
            'source' => $c->source,
            'items_count' => $c->itemsCount,
            'created_at' => $c->createdAt,
            'cost_usd' => $c->costUsd,
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
            'image_url' => $t->imageUrl,
            'image_author' => $t->imageAuthor,
            'image_author_url' => $t->imageAuthorUrl,
            'cefr' => $t->cefr,
            'source' => $t->source,
            'created_at' => $t->createdAt,
            'updated_at' => $t->updatedAt,
            'translations' => array_map(static fn (TermTranslationRow $r): array => [
                'lang' => $r->lang, 'text' => $r->text, 'is_primary' => $r->isPrimary,
            ], $t->translations),
            'examples' => array_map(self::termExample(...), $t->examples),
            'collections' => array_map(static fn ($r): array => [
                'id' => $r->id, 'title' => $r->title, 'type' => $r->type,
            ], $t->collections),
            'accepted_variants' => array_map(static fn (TermVariantRow $r): array => [
                'text' => $r->text, 'note' => $r->note, 'generator_version' => $r->generatorVersion,
            ], $t->acceptedVariants),
            'findings' => array_map(static fn (EnrichmentFindingRow $r): array => [
                'kind' => $r->kind,
                'field' => $r->field,
                'detail' => $r->detail,
                'generator_version' => $r->generatorVersion,
                'created_at' => $r->createdAt,
            ], $t->findings),
            'enrichment_version' => $t->enrichmentVersion,
            'progress_count' => $t->progressCount,
        ];
    }

    /** @return array<string, mixed> */
    public static function termExample(TermExampleRow $e): array
    {
        return [
            'id' => $e->id,
            'sentence' => $e->sentence,
            'translation' => $e->translation,
            'is_pinned' => $e->isPinned,
            'distractors' => array_map(static fn (ExampleDistractorRow $d): array => [
                'id' => $d->id,
                'sentence' => $d->sentence,
                'error_type' => $d->errorType,
                'error_span' => $d->errorSpan,
                'correction' => $d->correction,
                'generator_version' => $d->generatorVersion,
            ], $e->distractors),
        ];
    }

    // ── «Здоровье контента» ─────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function contentHealthSummary(ContentHealthSummary $s): array
    {
        return [
            // The three slices are NOT a partition: a term in a store collection and in someone's
            // own list counts in both, and a term in no collection only in `all`.
            'scopes' => [
                'all' => self::contentHealthScope($s->all),
                'system' => self::contentHealthScope($s->system),
                'user' => self::contentHealthScope($s->user),
            ],
            'collections' => array_map(self::contentHealthCollection(...), $s->collections),
            'suppressions' => [
                'total' => $s->suppressionsTotal,
                'by_source' => array_map(self::contentLabelCount(...), $s->suppressionsBySource),
            ],
            'generation_rejections' => [
                'total' => $s->rejectionsTotal,
                'by_field' => array_map(self::contentLabelCount(...), $s->rejectionsByField),
            ],
            'current_generator_version' => $s->currentGeneratorVersion,
            'min_distractors' => $s->minDistractors,
            'cost_per_term_usd' => $s->costPerTermUsd,
        ];
    }

    /** @return array<string, mixed> */
    public static function contentHealthScope(ContentHealthScope $s): array
    {
        return [
            'scope' => $s->scope,
            'terms' => $s->terms,
            'with_distractors' => $s->withDistractors,
            'pick_correct_ready' => $s->pickCorrectReady,
            'with_variants' => $s->withVariants,
            'without_example' => $s->withoutExample,
            'needs_enrichment' => $s->needsEnrichment,
            'estimated_topup_usd' => $s->estimatedTopUpUsd,
            'enrichment_versions' => array_map(static fn (ContentVersionCount $v): array => [
                'version' => $v->version,
                'terms' => $v->terms,
            ], $s->enrichmentVersions),
        ];
    }

    /** @return array<string, mixed> */
    public static function contentHealthCollection(ContentHealthCollectionRow $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'type' => $c->type,
            'terms' => $c->terms,
            'without_example' => $c->withoutExample,
            'pick_correct_ready' => $c->pickCorrectReady,
            'needs_enrichment' => $c->needsEnrichment,
            'estimated_topup_usd' => $c->estimatedTopUpUsd,
        ];
    }

    /** @return array<string, mixed> */
    public static function collectionContentHealth(CollectionContentHealth $c): array
    {
        return [
            'collection_id' => $c->collectionId,
            'title' => $c->title,
            'type' => $c->type,
            'terms' => array_map(self::contentHealthTerm(...), $c->terms),
            'needs_enrichment' => $c->needsEnrichment,
            'without_example' => $c->withoutExample,
            'pick_correct_ready' => $c->pickCorrectReady,
            'estimated_topup_usd' => $c->estimatedTopUpUsd,
            'topup_command' => $c->topUpCommand,
            'min_distractors' => $c->minDistractors,
            'cost_per_term_usd' => $c->costPerTermUsd,
        ];
    }

    /** @return array<string, mixed> */
    public static function contentHealthTerm(ContentHealthTermRow $t): array
    {
        return [
            'term_id' => $t->termId,
            'text' => $t->text,
            'translation' => $t->translation,
            'has_example' => $t->hasExample,
            // Kept apart from `needs_enrichment` on purpose: a term with no example is cured by
            // regenerating the example, never by the станок.
            'missing_example' => $t->missingExample,
            'usable_distractors' => $t->usableDistractors,
            'raw_distractors' => $t->rawDistractors,
            'pick_correct_ready' => $t->pickCorrectReady,
            'variants' => $t->variants,
            'enrichment_version' => $t->enrichmentVersion,
            'needs_enrichment' => $t->needsEnrichment,
            'needs_enrichment_reasons' => $t->needsEnrichmentReasons,
        ];
    }

    /** @return array<string, mixed> */
    public static function contentLabelCount(ContentLabelCount $c): array
    {
        return ['label' => $c->label, 'count' => $c->count];
    }

    /**
     * The term passport. Two things it deliberately does NOT carry: a ladder rung (that is
     * `/users/{id}/ladder`'s answer to a different question) and any way to START the enrichment
     * run — `topup_command` is a line to paste, and the panel never executes it.
     *
     * @return array<string, mixed>
     */
    public static function termContentPassport(TermContentPassport $p): array
    {
        return [
            'term_id' => $p->termId,
            'text' => $p->text,
            'lang' => $p->lang,
            'type' => $p->type,
            'translations' => array_map(static fn (TermTranslationRow $r): array => [
                'lang' => $r->lang, 'text' => $r->text, 'is_primary' => $r->isPrimary,
            ], $p->translations),
            'example' => $p->exampleId === null ? null : [
                'id' => $p->exampleId,
                'sentence' => $p->exampleSentence,
                'translation' => $p->exampleTranslation,
            ],
            'distractors' => array_map(static fn (PassportDistractorRow $d): array => [
                'id' => $d->id,
                'sentence' => $d->sentence,
                'error_type' => $d->errorType,
                'error_span' => $d->errorSpan,
                'correction' => $d->correction,
                'generator_version' => $d->generatorVersion,
                // false = a card would not deal this row: another distractor already breaks the
                // same span, and one error per card is the shape the trainer is for.
                'usable' => $d->usable,
            ], $p->distractors),
            'error_type_note' => 'Ярлык error_type — догадка станка о том, какую ошибку он написал; ничем не проверяется. '
                . 'Читайте сам фрагмент и исправление, ярлык — только подсказка.',
            // Their own list, never merged with the live rows: a suppression outlives the distractor
            // it was about, and even the example it hung off.
            'suppressed' => array_map(static fn (array $s): array => [
                'sentence' => $s['sentence'],
                'source' => $s['source'],
                'created_at' => $s['created_at'],
            ], $p->suppressed),
            'accepted_variants' => array_map(static fn (TermVariantRow $r): array => [
                'text' => $r->text, 'note' => $r->note, 'generator_version' => $r->generatorVersion,
            ], $p->acceptedVariants),
            'enrichment_versions' => array_map(static fn (array $v): array => [
                'version' => $v['version'], 'created_at' => $v['created_at'],
            ], $p->enrichmentVersions),
            'enrichment_version' => $p->enrichmentVersion,
            'findings' => array_map(static fn (EnrichmentFindingRow $r): array => [
                'kind' => $r->kind,
                'field' => $r->field,
                'detail' => $r->detail,
                'generator_version' => $r->generatorVersion,
                'created_at' => $r->createdAt,
            ], $p->findings),
            'simulation' => array_map(static fn (ModeSimulationRow $m): array => [
                'mode' => $m->mode,
                'status' => $m->status,
                'reason' => $m->reason,
                'explanation' => $m->explanation,
            ], $p->simulation),
            'usable_distractors' => $p->usableDistractors,
            // Separate from `needs_enrichment` on purpose: no example = regenerate the example, and
            // the станок cannot help.
            'missing_example' => $p->missingExample,
            'needs_enrichment' => $p->needsEnrichment,
            'needs_enrichment_reasons' => $p->needsEnrichmentReasons,
            'collections' => array_map(static fn (CollectionRefRow $c): array => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->type,
            ], $p->collections),
            'topup_command' => $p->topUpCommand,
            'topup_hint' => $p->topUpHint,
            'current_generator_version' => $p->currentGeneratorVersion,
            'min_distractors' => $p->minDistractors,
            'cost_per_term_usd' => $p->costPerTermUsd,
        ];
    }

    // ── «Песочница» ─────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function playgroundProvider(PlaygroundProviderRow $p): array
    {
        return [
            'provider' => $p->provider,
            'label' => $p->label,
            'models' => $p->models,
            'available' => $p->available,
            // Names the env var when unavailable — the one thing needed to fix it.
            'reason' => $p->reason,
        ];
    }

    /** @return array<string, mixed> */
    public static function playgroundResult(PlaygroundResult $r): array
    {
        return [
            'provider' => $r->provider,
            'model' => $r->model,
            'raw_text' => $r->rawText,
            'parsed_json' => $r->parsedJson,
            // The model answered but not in JSON — an ordinary result in a prompt sandbox.
            'parse_error' => $r->parseError,
            'usage' => [
                'tokens_in' => $r->tokensIn,
                'tokens_out' => $r->tokensOut,
                // Priced by the app's one pricing table; null = unpriced model, never free.
                'cost_usd' => $r->costUsd,
            ],
            'latency_ms' => $r->latencyMs,
            // The vendor never answered at all: auth, credits, timeout, a refusal.
            'error' => $r->error,
        ];
    }

    /** @return array<string, mixed> */
    public static function playgroundValidation(PlaygroundValidation $v): array
    {
        return [
            'items' => array_map(static fn (PlaygroundValidationRow $r): array => [
                'index' => $r->index,
                'sentence' => $r->sentence,
                'error_span' => $r->errorSpan,
                'correction' => $r->correction,
                'error_type' => $r->errorType,
                'verdict' => $r->kept ? 'KEEP' : 'REJECT',
                'gate' => $r->gate,
                'reason' => $r->reason,
                'error_type_defaulted' => $r->errorTypeDefaulted,
            ], $v->items),
            'kept' => $v->kept,
            'total' => $v->total,
            'source' => $v->source,
            'term_id' => $v->termId,
            'term_text' => $v->termText,
            'example_sentence' => $v->exampleSentence,
            'existing_count' => $v->existingCount,
            'suppressed_count' => $v->suppressedCount,
            'matched_term_id' => $v->matchedTermId,
            // Said in the payload as well as on the screen: this endpoint writes nothing, ever.
            'persisted' => false,
        ];
    }

    /** @return array<string, mixed> */
    public static function termImpact(TermImpact $i): array
    {
        return [
            'term_id' => $i->termId,
            'text' => $i->text,
            'collections_count' => $i->collectionsCount,
            'users_with_progress' => $i->usersWithProgress,
            'reviews_count' => $i->reviewsCount,
        ];
    }

    /** @return array<string, mixed> */
    public static function collectionImpact(CollectionImpact $i): array
    {
        return [
            'collection_id' => $i->collectionId,
            'title' => $i->title,
            'type' => $i->type,
            'owner_id' => $i->ownerId,
            'terms_count' => $i->termsCount,
            'subscribers' => $i->subscribers,
            'learners_with_progress' => $i->learnersWithProgress,
        ];
    }

    /** @return array<string, mixed> */
    public static function costByPurpose(CostByPurposeView $c): array
    {
        return [
            'scope_id' => $c->scopeId,
            'period' => $c->period,
            'since' => $c->since,
            'total_usd' => $c->totalUsd,
            'tokens_in' => $c->tokensIn,
            'tokens_out' => $c->tokensOut,
            'by_purpose' => array_map(static fn (PurposeCost $p): array => [
                'purpose' => $p->purpose,
                'tokens_in' => $p->tokensIn,
                'tokens_out' => $p->tokensOut,
                'cost_usd' => $p->costUsd,
                'calls' => $p->calls,
            ], $c->byPurpose),
            'note' => $c->note,
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
            'purpose' => $r->purpose,
            'collection_id' => $r->collectionId,
            'status' => $r->status,
            'duration_ms' => $r->durationMs,
            'user_id' => $r->userId,
            'occurred_at' => $r->occurredAt,
            'model' => $r->model,
            'tokens_in' => $r->tokensIn,
            'tokens_out' => $r->tokensOut,
            'cost_usd' => $r->costUsd,
            'error' => $r->error,
        ];
    }

    /** @return array<string, mixed> */
    public static function requestLogDetail(RequestLogDetail $d): array
    {
        return self::requestLog($d->row) + [
            'request_bytes' => $d->requestBytes,
            'response_bytes' => $d->responseBytes,
            'request_headers' => $d->requestHeaders,
            'request_body' => $d->requestBody,
            'response_body' => $d->responseBody,
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
