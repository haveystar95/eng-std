<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One word or phrase of a plan day, with the stage it stands on — the day screen's register
 * (макет «Фаза 4», кадр 1c · 02).
 *
 * The STAGE is the point of this DTO and the reason the day endpoint cannot just hand the client a
 * collection id and let it read its own mirror: a stage is not stored anywhere. It is a pure
 * function of the review log, computed on every read ({@see
 * \App\Modules\Learning\Domain\Service\PlanStageLadder}), and the device has no way to arrive at it
 * — the log it mirrors is answers, not the plan's ladder over them.
 */
final readonly class PlanDayTermView
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        /** `word | phrase | idiom | phrasal_verb` — the screen sets phrases and words differently. */
        public string $type,
        /** `a` | `b` | `c`. */
        public string $stage,
        public bool $stageComplete,
        public bool $finished,
        /**
         * The day that INTRODUCED this term. Equal to the day being read for its own words, smaller
         * for one carried in from an earlier day — which is what «B · со дня 1» is drawn from.
         */
        public int $fromDayIndex,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->termId,
            'text' => $this->text,
            'translation' => $this->translation,
            'type' => $this->type,
            'stage' => $this->stage,
            'stage_complete' => $this->stageComplete,
            'finished' => $this->finished,
            'from_day_index' => $this->fromDayIndex,
        ];
    }
}
