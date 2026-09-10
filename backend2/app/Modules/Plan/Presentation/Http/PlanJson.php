<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http;

use App\Modules\Plan\Application\Dto\CardView;
use App\Modules\Plan\Application\Dto\DayMetricsView;
use App\Modules\Plan\Application\Dto\DayRoomView;
use App\Modules\Plan\Application\Dto\DayRouteView;
use App\Modules\Plan\Application\Dto\DaySlotView;
use App\Modules\Plan\Application\Dto\PlanBuildView;
use App\Modules\Plan\Application\Dto\PlanSummaryView;
use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Dto\ProgramUnitView;
use App\Modules\Plan\Application\Dto\SceneView;
use App\Modules\Plan\Application\Dto\SheetView;
use App\Modules\Plan\Application\Dto\StageProgressView;
use App\Modules\Plan\Application\Dto\TermView;
use App\Modules\Plan\Application\Dto\VersionsView;

/**
 * The read models → the wire, snake_case, in one place so the tab, the preview and the day room
 * spell the same field the same way (`docs/plan-api.md`). Dates and numbers go raw; every
 * event-inflecting string arrives ready from the server.
 */
final class PlanJson
{
    /** @return array<string, mixed> */
    public static function plan(PlanView $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status,
            'goal_text' => $p->goalText,
            'target_lang' => $p->targetLang,
            'native_lang' => $p->nativeLang,
            'level' => $p->level,
            'days_total' => $p->daysTotal,
            'days_requested' => $p->daysRequested,
            'days_shortened_from' => $p->daysShortenedFrom,
            'event_date' => $p->eventDate,
            'days_left' => $p->daysLeft,
            'title_native' => $p->titleNative,
            'title_target' => $p->titleTarget,
            'event_native' => $p->eventNative,
            'until_phrase' => $p->untilPhrase,
            'overdue_native' => $p->overdueNative,
            'route_summary' => $p->routeSummary,
            'learner_role_target' => $p->learnerRoleTarget,
            'learner_role_native' => $p->learnerRoleNative,
            'cover_image' => $p->coverImage,
            'collection_id' => $p->collectionId,
            'unclear_reason' => $p->unclearReason,
            'fail_reason' => $p->failReason,
            'current_day' => $p->currentDay === null ? null : self::day($p->currentDay),
            'days' => array_map(self::day(...), $p->days),
            'scenes' => array_map(self::scene(...), $p->scenes),
            'rescue_kit' => $p->rescueKit,
            'cost_usd' => $p->costUsd,
            'versions' => self::versions($p->versions),
            'started_at' => $p->startedAt,
            'finished_at' => $p->finishedAt,
            'created_at' => $p->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(PlanSummaryView $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status,
            'title_native' => $p->titleNative,
            'goal_text' => $p->goalText,
            'event_date' => $p->eventDate,
            'days_total' => $p->daysTotal,
            'collection_id' => $p->collectionId,
            'started_at' => $p->startedAt,
            'finished_at' => $p->finishedAt,
            'created_at' => $p->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function build(PlanBuildView $b): array
    {
        return [
            'id' => $b->id,
            'status' => $b->status,
            'unclear_reason' => $b->unclearReason,
            'fail_reason' => $b->failReason,
            'scenes_count' => $b->scenesCount,
            'cost_usd' => $b->costUsd,
            'latency_ms' => $b->latencyMs,
            'attempts' => $b->attempts,
            'versions' => self::versions($b->versions),
        ];
    }

    /** @return array<string, string> */
    public static function versions(VersionsView $v): array
    {
        return ['build' => $v->build, 'prompt_plan' => $v->promptPlan, 'prompt_lesson' => $v->promptLesson];
    }

    /** @return array<string, mixed> */
    public static function scene(SceneView $s): array
    {
        return [
            'id' => $s->id,
            'order' => $s->order,
            'kind' => $s->kind,
            'priority' => $s->priority,
            'title_native' => $s->titleNative,
            'title_target' => $s->titleTarget,
            'teaches_native' => $s->teachesNative,
            'goals_native' => $s->goalsNative,
            'learner_role_target' => $s->learnerRoleTarget,
            'learner_role_native' => $s->learnerRoleNative,
            'partner_role_target' => $s->partnerRoleTarget,
            'partner_role_native' => $s->partnerRoleNative,
            'image' => $s->image,
            'lesson_status' => $s->lessonStatus,
            'lesson_fail_reason' => $s->lessonFailReason,
            'day_number' => $s->dayNumber,
            'cost_usd' => $s->costUsd,
            'latency_ms' => $s->latencyMs,
            'prompt_version' => $s->promptVersion,
        ];
    }

    /** @return array<string, mixed> */
    public static function day(DayRouteView $d): array
    {
        return [
            'id' => $d->id,
            'number' => $d->number,
            'type' => $d->type,
            'status' => $d->status,
            'scene_id' => $d->sceneId,
            'title_native' => $d->titleNative,
            'title_target' => $d->titleTarget,
            'teaches_native' => $d->teachesNative,
            'lesson_status' => $d->lessonStatus,
            'opens_on' => $d->opensOn,
            'slot' => self::slot($d->slot),
            'cards_total' => $d->cardsTotal,
            'cards_done' => $d->cardsDone,
            'minutes_spent' => $d->minutesSpent,
            'opened_at' => $d->openedAt,
            'closed_at' => $d->closedAt,
        ];
    }

    /** @return array<string, mixed> */
    public static function slot(DaySlotView $s): array
    {
        return ['code' => $s->code, 'date' => $s->date, 'label_native' => $s->labelNative];
    }

    /** @return array<string, mixed> */
    public static function room(DayRoomView $r): array
    {
        return [
            'plan_id' => $r->planId,
            'day' => self::day($r->day),
            'scene' => $r->scene === null ? null : self::scene($r->scene),
            'goals_native' => $r->goalsNative,
            'stages' => array_map(static fn (StageProgressView $s): array => [
                'stage' => $s->stage, 'total' => $s->total, 'done' => $s->done, 'state' => $s->state,
            ], $r->stages),
            'metrics' => $r->metrics === null ? null : self::metrics($r->metrics),
            'program' => array_map(static fn (ProgramUnitView $u): array => [
                'unit_kind' => $u->unitKind,
                'unit_ref' => $u->unitRef,
                'scene_id' => $u->sceneId,
                'text_target' => $u->textTarget,
                'text_native' => $u->textNative,
                'source' => $u->source,
                'cards_total' => $u->cardsTotal,
                'cards_done' => $u->cardsDone,
                'state' => $u->state,
            ], $r->program),
            'sheet_available' => $r->sheetAvailable,
        ];
    }

    /** @return array<string, mixed> */
    public static function metrics(DayMetricsView $m): array
    {
        return [
            'cards_total' => $m->cardsTotal,
            'cards_done' => $m->cardsDone,
            'minutes_spent' => $m->minutesSpent,
            'first_try_share' => $m->firstTryShare,
            'hardest_unit_kind' => $m->hardestUnitKind,
            'hardest_unit_ref' => $m->hardestUnitRef,
            'hardest_unit_text' => $m->hardestUnitText,
        ];
    }

    /** @return array<string, mixed> */
    public static function card(CardView $c): array
    {
        return [
            'id' => $c->id,
            'stage' => $c->stage,
            'position' => $c->position,
            'kind' => $c->kind,
            'source' => $c->source,
            'source_day_id' => $c->sourceDayId,
            'unit_kind' => $c->unitKind,
            'unit_ref' => $c->unitRef,
            'payload' => $c->payload,
            'retry_of' => $c->retryOf,
            'result' => $c->result,
            'attempts' => $c->attempts,
            'answered_at' => $c->answeredAt,
            'returns' => $c->returns,
        ];
    }

    /** @return array<string, mixed> */
    public static function sheet(SheetView $s): array
    {
        return [
            'plan_id' => $s->planId,
            'number' => $s->number,
            'words' => array_map(self::term(...), $s->words),
            'phrases' => array_map(self::term(...), $s->phrases),
        ];
    }

    /** @return array<string, mixed> */
    public static function term(TermView $t): array
    {
        return [
            'id' => $t->id,
            'scene_id' => $t->sceneId,
            'kind' => $t->kind,
            'ref' => $t->ref,
            'text_target' => $t->textTarget,
            'text_native' => $t->textNative,
            'pronunciation_native' => $t->pronunciationNative,
            'definition_target' => $t->definitionTarget,
            'example_target' => $t->exampleTarget,
            'example_native' => $t->exampleNative,
            'speaking_key' => $t->speakingKey,
            'simplified_variants' => $t->simplifiedVariants,
            'image' => $t->image,
        ];
    }
}
