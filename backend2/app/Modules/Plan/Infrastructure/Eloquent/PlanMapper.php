<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Blueprint\PlanTitles;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Plan\Domain\ValueObject\SceneKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Rows ↔ the plan aggregate. The lesson is stored as the JSON the model wrote and re-parsed on read.
 * The JSON columns are handed to Eloquent as arrays: the models cast them, and a pre-encoded string
 * would be encoded twice.
 */
final class PlanMapper
{
    public function __construct(private readonly LessonParser $lessons = new LessonParser) {}

    public function toDomain(PlanModel $row): Plan
    {
        $planId = PlanId::fromString($row->id);
        $titles = $row->title_native === null ? null : new PlanTitles(
            titleNative: (string) $row->title_native,
            titleTarget: (string) $row->title_target,
            eventNative: (string) $row->event_native,
            untilPhraseNative: (string) $row->until_phrase_native,
            overdueNative: (string) $row->overdue_native,
            coverImagePrompt: (string) $row->cover_image_prompt,
            learnerRoleTarget: (string) $row->learner_role_target,
            learnerRoleNative: (string) $row->learner_role_native,
        );

        return Plan::reconstitute(
            id: $planId,
            userId: UserId::fromString($row->user_id),
            goalText: $row->goal_text,
            targetLang: new LanguageCode($row->target_lang),
            nativeLang: new LanguageCode($row->native_lang),
            level: PlanLevel::from($row->level),
            daysTotal: $row->days_total,
            daysRequested: $row->days_requested,
            eventDate: self::date($row->event_date),
            status: PlanStatus::from($row->status),
            titles: $titles,
            coverImage: self::image($row->cover_image_url, $row->cover_image_author, $row->cover_image_author_url),
            planCall: self::call($row->prompt_version_plan, $row->build_version, $row->model_plan, $row->cost_usd_plan, $row->latency_ms_plan, $row->attempts_plan),
            findings: self::findings($row->checks_json),
            unclearReason: $row->unclear_reason,
            failReason: $row->fail_reason,
            buildStartedAt: self::instant($row->build_started_at),
            collectionId: $row->collection_id === null ? null : CollectionId::fromString($row->collection_id),
            startedAt: self::instant($row->started_at),
            finishedAt: self::instant($row->finished_at),
            createdAt: self::instant($row->created_at) ?? new DateTimeImmutable,
            scenes: array_values($row->scenes->map(fn (PlanSceneModel $s): PlanScene => $this->scene($s, $planId))->all()),
            days: array_values($row->days->map(fn (PlanDayModel $d): PlanDay => $this->day($d, $planId))->all()),
        );
    }

    /** One scene row on its own — what a scene-addressed job reads and writes. */
    public function sceneOf(PlanSceneModel $row): PlanScene
    {
        return $this->scene($row, PlanId::fromString($row->plan_id));
    }

    private function scene(PlanSceneModel $row, PlanId $planId): PlanScene
    {
        $lesson = null;
        if (is_array($row->lesson_json)) {
            $lesson = $this->lessons->parse($row->lesson_json);
        }

        return PlanScene::reconstitute(
            id: PlanSceneId::fromString($row->id),
            planId: $planId,
            order: $row->order,
            kind: SceneKind::from($row->kind),
            priority: $row->priority,
            titleNative: $row->title_native,
            titleTarget: $row->title_target,
            teachesNative: $row->teaches_native,
            goalsNative: array_map('strval', $row->goals_native),
            learnerRoleTarget: $row->learner_role_target,
            learnerRoleNative: $row->learner_role_native,
            partnerRoleTarget: $row->partner_role_target,
            partnerRoleNative: $row->partner_role_native,
            topicDescription: $row->topic_description,
            imagePrompt: $row->image_prompt,
            image: self::image($row->image_url, $row->image_author, $row->image_author_url),
            lesson: $lesson,
            lessonStatus: LessonStatus::from($row->lesson_status),
            lessonCall: self::call($row->prompt_version_lesson, $row->build_version, $row->model_lesson, $row->cost_usd_lesson, $row->latency_ms_lesson, $row->attempts_lesson),
            findings: self::findings($row->checks_json),
            failReason: $row->fail_reason,
            buildStartedAt: self::instant($row->build_started_at),
            generatedAt: self::instant($row->generated_at),
        );
    }

    private function day(PlanDayModel $row, PlanId $planId): PlanDay
    {
        return PlanDay::reconstitute(
            id: PlanDayId::fromString($row->id),
            planId: $planId,
            number: $row->number,
            type: DayType::from($row->type),
            sceneId: $row->scene_id === null ? null : PlanSceneId::fromString($row->scene_id),
            status: DayStatus::from($row->status),
            opensOn: self::date($row->opens_on),
            openedAt: self::instant($row->opened_at),
            closedAt: self::instant($row->closed_at),
            metrics: new DayMetrics(
                cardsTotal: $row->cards_total,
                cardsDone: $row->cards_done,
                minutesSpent: $row->minutes_spent,
                firstTryShare: $row->first_try_share === null ? null : (float) $row->first_try_share,
                hardestUnitKind: $row->hardest_unit_kind === null ? null : UnitKind::from($row->hardest_unit_kind),
                hardestUnitRef: $row->hardest_unit_ref,
                hardestUnitText: $row->hardest_unit_text,
            ),
        );
    }

    /** @return array<string, mixed> */
    public function planColumns(Plan $plan): array
    {
        $titles = $plan->titles();
        $call = $plan->planCall();

        return [
            'user_id' => $plan->userId()->value,
            'goal_text' => $plan->goalText(),
            'target_lang' => $plan->targetLang()->value,
            'native_lang' => $plan->nativeLang()->value,
            'level' => $plan->level()->value,
            'days_total' => $plan->daysTotal(),
            'days_requested' => $plan->daysRequested(),
            'event_date' => $plan->eventDate()?->format('Y-m-d'),
            'status' => $plan->status()->value,
            'title_native' => $titles?->titleNative,
            'title_target' => $titles?->titleTarget,
            'event_native' => $titles?->eventNative,
            'until_phrase_native' => $titles?->untilPhraseNative,
            'overdue_native' => $titles?->overdueNative,
            'cover_image_prompt' => $titles?->coverImagePrompt,
            'learner_role_target' => $titles?->learnerRoleTarget,
            'learner_role_native' => $titles?->learnerRoleNative,
            'cover_image_url' => $plan->coverImage()?->url,
            'cover_image_author' => $plan->coverImage()?->author,
            'cover_image_author_url' => $plan->coverImage()?->authorUrl,
            'prompt_version_plan' => $call?->promptVersion,
            'build_version' => $call?->buildVersion,
            'model_plan' => $call?->model,
            'cost_usd_plan' => $call?->costUsd,
            'latency_ms_plan' => $call?->latencyMs,
            'attempts_plan' => $call?->attempts,
            'checks_json' => $plan->findings(),
            'unclear_reason' => $plan->unclearReason(),
            'fail_reason' => $plan->failReason(),
            'build_started_at' => $plan->buildStartedAt()?->format(DATE_ATOM),
            'collection_id' => $plan->collectionId()?->value,
            'started_at' => $plan->startedAt()?->format(DATE_ATOM),
            'finished_at' => $plan->finishedAt()?->format(DATE_ATOM),
            'created_at' => $plan->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * A scene's own columns — no `plan_id`, no `user_id`: both are set when the row is created and
     * neither may be rewritten by a pointwise update.
     *
     * @return array<string, mixed>
     */
    public function sceneColumns(PlanScene $scene): array
    {
        $call = $scene->lessonCall();

        return [
            'order' => $scene->order(),
            'kind' => $scene->kind()->value,
            'priority' => $scene->priority(),
            'title_native' => $scene->titleNative(),
            'title_target' => $scene->titleTarget(),
            'teaches_native' => $scene->teachesNative(),
            'goals_native' => $scene->goalsNative(),
            'learner_role_target' => $scene->learnerRoleTarget(),
            'learner_role_native' => $scene->learnerRoleNative(),
            'partner_role_target' => $scene->partnerRoleTarget(),
            'partner_role_native' => $scene->partnerRoleNative(),
            'topic_description' => $scene->topicDescription(),
            'image_prompt' => $scene->imagePrompt(),
            'image_url' => $scene->image()?->url,
            'image_author' => $scene->image()?->author,
            'image_author_url' => $scene->image()?->authorUrl,
            'lesson_json' => $scene->lesson()?->toArray(),
            'lesson_status' => $scene->lessonStatus()->value,
            'prompt_version_lesson' => $call?->promptVersion,
            'build_version' => $call?->buildVersion,
            'model_lesson' => $call?->model,
            'cost_usd_lesson' => $call?->costUsd,
            'latency_ms_lesson' => $call?->latencyMs,
            'attempts_lesson' => $call?->attempts,
            'checks_json' => $scene->findings(),
            'fail_reason' => $scene->failReason(),
            'build_started_at' => $scene->buildStartedAt()?->format(DATE_ATOM),
            'generated_at' => $scene->generatedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * A day's own columns — no `plan_id`, no `user_id`, for the reason {@see sceneColumns()} gives.
     *
     * @return array<string, mixed>
     */
    public function dayColumns(PlanDay $day): array
    {
        return [
            'number' => $day->number(),
            'type' => $day->type()->value,
            'scene_id' => $day->sceneId()?->value,
            'status' => $day->status()->value,
            'opens_on' => $day->opensOn()?->format('Y-m-d'),
            'opened_at' => $day->openedAt()?->format(DATE_ATOM),
            'closed_at' => $day->closedAt()?->format(DATE_ATOM),
            ...self::metricColumns($day->metrics()),
        ];
    }

    /**
     * The day's numbers alone — the columns an answer refreshes without touching the calendar.
     *
     * @return array<string, mixed>
     */
    public static function metricColumns(DayMetrics $m): array
    {
        return [
            'cards_total' => $m->cardsTotal,
            'cards_done' => $m->cardsDone,
            'minutes_spent' => $m->minutesSpent,
            'first_try_share' => $m->firstTryShare,
            'hardest_unit_kind' => $m->hardestUnitKind?->value,
            'hardest_unit_ref' => $m->hardestUnitRef,
            'hardest_unit_text' => $m->hardestUnitText,
        ];
    }

    private static function call(?string $prompt, ?string $build, ?string $model, ?string $cost, ?int $latency, ?int $attempts): ?ModelCall
    {
        if ($prompt === null || $model === null) {
            return null;
        }

        return new ModelCall($prompt, (string) $build, $model, $cost ?? '0.000000', $latency ?? 0, $attempts ?? 1);
    }

    private static function image(?string $url, ?string $author, ?string $authorUrl): ?Image
    {
        return $url === null || trim($url) === '' ? null : new Image($url, $author, $authorUrl);
    }

    /** @return list<array{check: string, mode: string, action: string, detail: string}> */
    private static function findings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (is_array($row) && isset($row['check'], $row['mode'], $row['action'], $row['detail'])) {
                $out[] = [
                    'check' => (string) $row['check'],
                    'mode' => (string) $row['mode'],
                    'action' => (string) $row['action'],
                    'detail' => (string) $row['detail'],
                ];
            }
        }

        return $out;
    }

    /** Eloquent hands timestamps back as Carbon and dates as strings; the aggregate wants neither. */
    private static function date(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format('Y-m-d'));
        }

        return is_string($value) && $value !== '' ? new DateTimeImmutable(substr($value, 0, 10)) : null;
    }

    private static function instant(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
