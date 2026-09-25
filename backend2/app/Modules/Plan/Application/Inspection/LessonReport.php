<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\DayType;

/**
 * THE LESSON A DAY WAS DEALT FROM, AND ITS VOICE (наряд ADM-1, «Урок и голос»). The lesson is the SERVED one — what every
 * card of the day is dealt from ({@see \App\Modules\Plan\Domain\Lesson\LessonAssembly}: the learner lines assembled from
 * their frames, the `in_dialogue` marks as the lines say them), not the model's raw answer; the units returned from earlier
 * days are the day's cards whose source is `returned`. Beside it, every line the scene says with the voice its cast gives
 * it and the file bought for it ({@see VoiceTable}).
 */
final readonly class LessonReport
{
    public function __construct(private VoiceTable $voices) {}

    /** @return list<array<string, mixed>> */
    public function of(PlanInspectionData $data, ?int $number): array
    {
        return array_map(fn (PlanDay $day): array => $this->day($data, $day), $data->daysOf($number));
    }

    /** @return array<string, mixed> */
    private function day(PlanInspectionData $data, PlanDay $day): array
    {
        $scene = $day->type() === DayType::Scene ? $data->scene($day->sceneId()?->value) : null;
        $cards = $data->cardsOfDay($day);
        $lesson = $scene?->lesson();
        $lines = $scene === null ? [] : $this->voices->of($data, $scene);

        return [
            'number' => $day->number(),
            'type' => $day->type()->value,
            'scene' => $scene === null ? null : [
                'id' => $scene->id()->value,
                'title_native' => $scene->titleNative(),
                'title_target' => $scene->titleTarget(),
                'teaches_native' => $scene->teachesNative(),
                'goals_native' => $scene->goalsNative(),
                'topic_description' => $scene->topicDescription(),
                'lesson_status' => $scene->lessonStatus()->value,
                'partner_role_native' => $scene->partnerRoleNative(),
                'partner_role_target' => $scene->partnerRoleTarget(),
                'learner_role_native' => $scene->learnerRoleNative(),
                'learner_role_target' => $scene->learnerRoleTarget(),
                'role_gender' => $scene->answer()?->roleGender?->value,
                'partner_voice_gender' => $scene->partnerVoiceGender()?->value,
                'partner_voice_id' => $scene->partnerVoiceId(),
                'learner_voice_gender' => $data->profileGender?->value,
            ],
            'lesson' => $lesson?->toArray(),
            'scenes_covered' => $this->scenesCovered($data, $cards),
            'returns' => $this->returns($data, $cards),
            'voice' => [
                'lines' => array_map(static fn (VoiceLine $line): array => $line->toArray(), $lines),
                'voiced' => count(array_filter($lines, static fn (VoiceLine $l): bool => $l->status() === VoiceLine::VOICED)),
                'phone' => count(array_filter($lines, static fn (VoiceLine $l): bool => $l->status() === VoiceLine::PHONE)),
                'none' => count(array_filter($lines, static fn (VoiceLine $l): bool => $l->status() === VoiceLine::NONE)),
            ],
        ];
    }

    /**
     * The scenes a day's cards come from — for a review or the rehearsal, the scenes it goes over.
     *
     * @param  list<InspectedCard>  $cards
     * @return list<array{id: string, title_native: string|null, day: int|null}>
     */
    private function scenesCovered(PlanInspectionData $data, array $cards): array
    {
        $ids = [];
        foreach ($cards as $card) {
            $id = $card->payload['scene_id'] ?? null;
            if (is_string($id)) {
                $ids[$id] = true;
            }
        }

        return array_map(static fn (string $id): array => [
            'id' => $id,
            'title_native' => $data->sceneRow($id)?->titleNative,
            'day' => $data->dayOfScene($id),
        ], array_keys($ids));
    }

    /**
     * The units that came back from earlier days, each once, with the day they came from.
     *
     * @param  list<InspectedCard>  $cards
     * @return list<array<string, mixed>>
     */
    private function returns(PlanInspectionData $data, array $cards): array
    {
        $out = [];
        foreach ($cards as $card) {
            if ($card->source !== 'returned') {
                continue;
            }
            $key = $card->unitKind.':'.$card->unitRef.':'.$card->sourceDayId;
            $out[$key] ??= [
                'unit_kind' => $card->unitKind,
                'unit_ref' => $card->unitRef,
                'from_day' => $card->sourceDayId === null ? null : $data->dayNumberOf($card->sourceDayId),
                'scene_id' => is_string($card->payload['scene_id'] ?? null) ? $card->payload['scene_id'] : null,
                'cards' => 0,
            ];
            $out[$key]['cards']++;
        }

        return array_values($out);
    }
}
