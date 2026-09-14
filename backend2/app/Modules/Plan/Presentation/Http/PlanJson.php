<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http;

use App\Modules\Plan\Application\Dto\CardView;
use App\Modules\Plan\Application\Dto\DayRoomView;
use App\Modules\Plan\Application\Dto\DayRouteView;
use App\Modules\Plan\Application\Dto\DaySlotView;
use App\Modules\Plan\Application\Dto\DayWindowView;
use App\Modules\Plan\Application\Dto\PlanBuildView;
use App\Modules\Plan\Application\Dto\PlanLanguagesView;
use App\Modules\Plan\Application\Dto\PlanSummaryView;
use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Dto\ProgramUnitView;
use App\Modules\Plan\Application\Dto\RouteStageView;
use App\Modules\Plan\Application\Dto\SceneView;
use App\Modules\Plan\Application\Dto\StageProgressView;
use App\Modules\Plan\Application\Dto\VersionsView;
use App\Modules\Plan\Application\Dto\WindowGoalView;
use App\Modules\Plan\Application\Dto\WindowLineView;
use App\Modules\Plan\Application\Dto\WindowPairView;
use App\Modules\Plan\Application\Dto\WindowPhraseView;
use App\Modules\Plan\Application\Dto\WindowStageView;
use App\Modules\Plan\Application\Dto\WindowSummaryView;
use App\Modules\Plan\Application\Dto\WindowUsageView;
use App\Modules\Plan\Application\Dto\WindowWordView;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;

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
            'summary' => $p->summary,
            'reminder_hour' => $p->reminderHour,
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

    /** @return array{targets: list<string>} */
    public static function languages(PlanLanguagesView $l): array
    {
        return ['targets' => $l->targets];
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
            'image' => self::sceneImage($s),
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
            'stages' => array_map(static fn (RouteStageView $st): array => ['stage' => $st->stage, 'state' => $st->state], $d->stages),
        ];
    }

    /**
     * The scene photo: the vendor's address and credit, its tone, and the two square copies the
     * server keeps — absolute addresses built from the request (the phone comes through ngrok, the
     * simulator through localhost; proxies are trusted), each with the photo's version in the query
     * so the `immutable` they are served with is honest.
     *
     * @return array<string, string|null>|null
     */
    public static function sceneImage(SceneView $s): ?array
    {
        if ($s->image === null) {
            return null;
        }
        $address = static fn (SceneImageSize $size): string => url("/api/v1/plans/images/{$s->id}/{$size->value}").'?v='.$s->imageVersion;

        return [
            ...$s->image,
            'url_112' => $address(SceneImageSize::Small),
            'url_448' => $address(SceneImageSize::Large),
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
            'stages' => array_map(static fn (StageProgressView $s): array => [
                'stage' => $s->stage, 'total' => $s->total, 'done' => $s->done, 'state' => $s->state,
            ], $r->stages),
            'metrics' => $r->metrics === null ? null : ['cards_total' => $r->metrics->cardsTotal, 'minutes_spent' => $r->metrics->minutesSpent],
            'program' => array_map(static fn (ProgramUnitView $u): array => [
                'unit_kind' => $u->unitKind,
                'source' => $u->source,
                'state' => $u->state,
            ], $r->program),
            'window' => self::window($r->window),
        ];
    }

    /**
     * «Окно дня» (DAY-UI-2, DAY-UI-3). Counts, minutes and shares are the server's; the voice of a
     * line, a phrase or a word is an absolute address built from the request, like the photo crops.
     *
     * @return array<string, mixed>
     */
    public static function window(DayWindowView $w): array
    {
        $audio = static fn (?string $id): ?string => $id === null ? null : url("/api/v1/plans/audio/{$id}");
        $summary = static fn (WindowSummaryView $s): array => ['total' => $s->total, 'done' => $s->done, 'returns' => $s->returns];
        $line = static fn (?WindowLineView $l): ?array => $l === null ? null : [
            'text' => $l->text,
            'translation' => $l->translation,
            'audio_url' => $audio($l->audioId),
            ...($l->state === null ? [] : ['state' => $l->state]),
        ];
        $usage = static fn (?WindowUsageView $u): ?array => $u === null ? null : [
            'text' => $u->text,
            'translation' => $u->translation,
            'offset' => $u->offset,
            'length' => $u->length,
            'audio_url' => $audio($u->audioId),
        ];
        $scene = $w->day->scene;

        return [
            'day' => [
                'index' => $w->day->index,
                'type' => $w->day->type,
                'title_native' => $scene?->titleNative,
                'title_target' => $scene?->titleTarget,
                'image' => $scene === null ? null : self::sceneImage($scene),
                'image_tone' => $w->day->imageTone,
                'status' => $w->day->status,
                'minutes_estimate' => $w->day->minutesEstimate,
                'minutes_spent' => $w->day->minutesSpent,
                'goals' => array_map(static fn (WindowGoalView $g): array => ['text' => $g->text, 'passed' => $g->passed], $w->day->goals),
            ],
            'stages' => array_map(static fn (WindowStageView $s): array => [
                'stage' => $s->stage,
                'state' => $s->state,
                'done_count' => $s->doneCount,
                'total' => $s->total,
                'minutes_left' => $s->minutesLeft,
                'share' => $s->share,
            ], $w->stages),
            'day_progress' => $w->dayProgress,
            'program' => [
                'words' => [
                    'summary' => $summary($w->program->wordsSummary),
                    'items' => array_map(static fn (WindowWordView $v): array => [
                        'ref' => $v->ref,
                        'term' => $v->term,
                        'translation' => $v->translation,
                        'pronunciation' => $v->pronunciation,
                        'definition' => $v->definition,
                        'image' => $v->image,
                        'image_tone' => $v->imageTone,
                        'audio_url' => $audio($v->audioId),
                        'usage' => $usage($v->usage),
                        'state' => $v->state,
                        'returns_day' => $v->returnsDay,
                    ], $w->program->words),
                ],
                'phrases' => [
                    'summary' => $summary($w->program->phrasesSummary),
                    'items' => array_map(static fn (WindowPhraseView $v): array => [
                        'ref' => $v->ref,
                        'text' => $v->text,
                        'translation' => $v->translation,
                        'pronunciation' => $v->pronunciation,
                        'audio_url' => $audio($v->audioId),
                        'state' => $v->state,
                    ], $w->program->phrases),
                ],
                'dialogue' => [
                    'summary' => $summary($w->program->dialogueSummary),
                    'items' => array_map(static fn (WindowPairView $v): array => [
                        'step' => $v->step,
                        'partner' => $line($v->partner),
                        'learner' => $line($v->learner),
                    ], $w->program->dialogue),
                ],
            ],
            'allowed_action' => $w->allowedAction,
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
}
