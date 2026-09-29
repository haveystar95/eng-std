<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedScene;
use App\Modules\Plan\Application\Port\PlanCallJournal;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use DateTimeImmutable;

/**
 * THE CONVEYOR, DAY BY DAY (наряд ADM-1, «Конвейер генерации»): the plan's job → the day's lesson — its two stages, every
 * call the journal holds (наряд GEN-4) → the findings left, fatal or not by their rule ({@see LessonCodes::isFatal()}) → the
 * repairs → the seam judge → the photos → the voice → served to the client. Each stage says what is stored and, where the store has
 * nothing, says that («не хранится»), never a guess.
 */
final readonly class PipelineReport
{
    public function __construct(private PlanCallJournal $journal) {}

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    public function of(PlanInspectionData $data, ?int $number, array $calls): array
    {
        $days = $data->daysOf($number);
        $served = $this->journal->firstServed(array_map(static fn (PlanDay $d): string => 'api/v1/plans/'.$d->planId()->value.'/days/'.$d->number(), $days));

        return [
            'plan_build' => $this->planBuild($data, $calls),
            'days' => array_map(fn (PlanDay $day): array => $this->day($data, $day, $calls, $served), $days),
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    private function planBuild(PlanInspectionData $data, array $calls): array
    {
        $row = $data->row;
        $own = self::callsOf($calls, 'plan', $row->id, null);
        $ready = $data->firstEvent(PlanEventKind::PlanReady)?->occurredAt;

        // The stage's status is the BUILD's: done once `plan_ready` is written, else what the row says of the build.
        $status = $ready !== null ? 'done' : $row->status;

        return self::stage('plan', 'Job плана', $status, $row->buildStartedAt ?? $row->createdAt, $ready, [
            'model' => $row->model,
            'prompt_version' => $row->promptVersion,
            'build_version' => $row->buildVersion,
            'attempts' => $row->attempts,
            'latency_ms' => $row->latencyMs,
            'cost_usd' => $row->costUsd,
            'reason' => $row->failReason ?? $row->unclearReason,
            'checks' => $row->checks,
        ], $own, ['build_started_at перезаписывается пересборкой — окно вызовов только у последней сборки плана']);
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @param  array<string, DateTimeImmutable>  $served
     * @return array<string, mixed>
     */
    private function day(PlanInspectionData $data, PlanDay $day, array $calls, array $served): array
    {
        $scene = $day->type() === DayType::Scene ? $data->sceneRow($day->sceneId()?->value) : null;
        $path = 'api/v1/plans/'.$data->id().'/days/'.$day->number();
        // Served = the first time the client received the day's document (it may be read before the day is opened).
        $servedStage = self::stage('served', 'Выдан клиенту', $day->openedAt() !== null ? 'opened' : 'not_opened', $served[$path] ?? null, null, [
            'opened_at' => $day->openedAt()?->format(DATE_ATOM),
            'first_served_at' => ($served[$path] ?? null)?->format(DATE_ATOM),
            'path' => $path,
        ], [], []);

        if ($scene === null) {
            return [
                'number' => $day->number(),
                'type' => $day->type()->value,
                'scene_id' => null,
                'stages' => [$servedStage],
                'note' => 'У дня повторения/репетиции нет своего урока — он раздаётся из уроков прошлых сцен.',
            ];
        }

        $lessonCalls = self::callsOf($calls, 'scene', $scene->id, CallAttribution::LESSON_PURPOSES);
        $repairCalls = self::callsOf($calls, 'scene', $scene->id, CallAttribution::REPAIR_PURPOSES);
        $judgeCalls = self::callsOf($calls, 'scene', $scene->id, CallAttribution::JUDGE_PURPOSES);
        $findings = array_map(static fn (array $f): array => [
            'code' => (string) ($f['code'] ?? ''),
            'address' => $f['address'] ?? null,
            'detail' => $f['detail'] ?? null,
            'fatal' => LessonCodes::isFatal((string) ($f['code'] ?? '')),
        ], $scene->findings);
        $seams = array_values(array_filter($findings, static fn (array $f): bool => $f['code'] === LessonCodes::FILLER_NATIVE_SEAM));

        return [
            'number' => $day->number(),
            'type' => $day->type()->value,
            'scene_id' => $scene->id,
            'stages' => [
                self::stage('lesson', 'Урок дня', $scene->lessonStatus, $scene->buildStartedAt, $scene->builtAt, [
                    'model' => $scene->model,
                    'prompt_version' => $scene->promptVersion,
                    'build_version' => $scene->buildVersion,
                    'attempts' => $scene->attempts,
                    'latency_ms' => $scene->latencyMs,
                    'cost_usd' => $scene->costUsd,
                    'cost_note' => 'cost_usd сцены — обе ступени дня с повторами, починки и судья швов вместе',
                    'fail_reason' => $scene->failReason,
                ], $lessonCalls, [
                    'окно вызовов — только последняя сборка сцены (build_started_at перезаписывается)',
                    'день — две ступени (наряд GEN-4): вызовы skeleton и dialogue, у каждой один повтор по фатальной находке; attempts — вызовы обеих ступеней',
                    'время конца урока — plan_scenes.built_at (сцена стала ready, фото на месте); generated_at — момент НАЧАЛА сборки; у сцен без built_at окна нет',
                ]),
                self::stage('validator', 'Валидатор', $scene->failReason === null ? ($findings === [] ? 'clean' : 'warnings') : 'failed', null, null, [
                    'findings' => $findings,
                    'fatal' => count(array_filter($findings, static fn (array $f): bool => $f['fatal'])),
                    'warnings' => count(array_filter($findings, static fn (array $f): bool => ! $f['fatal'])),
                ], [], ['находки до починок не хранятся — checks_json держит только то, что осталось после починок обеих ступеней']),
                self::stage('repair', 'Починки P2R', $repairCalls === [] ? 'none' : 'done', self::first($repairCalls), self::last($repairCalls), [
                    'calls' => count($repairCalls),
                ], $repairCalls, ['какую карточку чинили и «было/стало» — не хранится (lesson_json и skeleton_json — после починок)', 'версия промта починки — не хранится']),
                self::stage('seam_judge', 'Судья швов', $judgeCalls === [] ? 'none' : 'done', self::first($judgeCalls), self::last($judgeCalls), [
                    'rejected' => $seams,
                ], $judgeCalls, ['версия промта судьи швов — не хранится', 'judge.unavailable — только в счётчиках plan_check_counters, не по сцене']),
                $this->images($data, $scene),
                $this->voice($data, $scene),
                $servedStage,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function images(PlanInspectionData $data, InspectedScene $scene): array
    {
        $terms = $data->termsOf($scene->id);
        $withPhoto = count(array_filter($terms, static fn ($t): bool => $t->image() !== null));
        $toneOnly = count(array_filter($terms, static fn ($t): bool => $t->image() === null && $t->imageTone() !== null));

        return self::stage('images', 'Картинки', $scene->imageUrl === null ? 'no_scene_photo' : 'done', null, null, [
            'scene_image_url' => $scene->imageUrl,
            'scene_image_author' => $scene->imageAuthor,
            'scene_image_tone' => $scene->imageTone,
            'terms' => count($terms),
            'terms_with_photo' => $withPhoto,
            'terms_without_photo' => $toneOnly,
            'terms_not_searched' => count($terms) - $withPhoto - $toneOnly,
        ], [], ['время поиска фото и цена не хранятся (Pexels бесплатно, вызовы в логе без ссылки на сцену)']);
    }

    /** @return array<string, mixed> */
    private function voice(PlanInspectionData $data, InspectedScene $scene): array
    {
        $audios = $data->audiosOfScene($scene->id);
        $dates = array_values(array_filter(array_map(static fn ($a): ?DateTimeImmutable => $a->createdAt, $audios)));
        $bill = DayMoney::voice($audios);

        return self::stage('voice', 'Озвучка', $audios === [] ? 'none' : 'done', $dates === [] ? null : min($dates), $dates === [] ? null : max($dates), [
            'lines' => $bill['lines'],
            'characters' => $bill['characters'],
            'credits' => $bill['credits'],
            'cost_usd' => $bill['cost_usd'],
        ], [], ['отказ вендора (402), предохранитель и кап — только в логе приложения и failed_jobs, не по сцене']);
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<AttributedCall>  $calls
     * @param  list<string>  $notStored
     * @return array<string, mixed>
     */
    private static function stage(string $key, string $title, string $status, ?DateTimeImmutable $from, ?DateTimeImmutable $to, array $facts, array $calls, array $notStored): array
    {
        $tokensIn = array_sum(array_map(static fn (AttributedCall $c): int => (int) $c->call->tokensIn, $calls));
        $tokensOut = array_sum(array_map(static fn (AttributedCall $c): int => (int) $c->call->tokensOut, $calls));

        return [
            'key' => $key,
            'title' => $title,
            'status' => $status,
            'started_at' => $from?->format(DATE_ATOM),
            'finished_at' => $to?->format(DATE_ATOM),
            'duration_ms' => $from !== null && $to !== null ? max(0, ($to->getTimestamp() - $from->getTimestamp()) * 1000) : null,
            'facts' => $facts,
            'calls' => array_map(static fn (AttributedCall $c): array => $c->toArray(), $calls),
            'tokens_in' => $calls === [] ? null : $tokensIn,
            'tokens_out' => $calls === [] ? null : $tokensOut,
            'calls_cost_usd' => $calls === [] ? null : round(array_sum(array_map(static fn (AttributedCall $c): float => (float) $c->call->costUsd, $calls)), 6),
            'not_stored' => $notStored,
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @param  list<string>|null  $purposes
     * @return list<AttributedCall>
     */
    private static function callsOf(array $calls, string $kind, string $subject, ?array $purposes): array
    {
        return array_values(array_filter($calls, static fn (AttributedCall $c): bool => $c->window->kind === $kind
            && $c->window->subjectId === $subject && ($purposes === null || in_array($c->call->purpose, $purposes, true))));
    }

    /** @param list<AttributedCall> $calls */
    private static function first(array $calls): ?DateTimeImmutable
    {
        return $calls === [] ? null : $calls[0]->call->startedAt;
    }

    /** @param list<AttributedCall> $calls */
    private static function last(array $calls): ?DateTimeImmutable
    {
        return $calls === [] ? null : ($calls[count($calls) - 1]->call->finishedAt ?? $calls[count($calls) - 1]->call->startedAt);
    }
}
