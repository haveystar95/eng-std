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
        /** `word | phrase | idiom | phrasal_verb` — what the expression IS, lexically. */
        public string $type,
        /**
         * `line | word | chunk` — what it DOES in this day, or null on a term that never came from
         * a plan day of v0.2 or later.
         *
         * The day screen sets a spoken LINE differently from a substitution, and until v0.2 it had
         * to guess that from `type` («anything that is not one word is a line»). The guess starts
         * lying the moment a connector appears: «deal with» is two words and a substitution.
         */
        public ?string $kind,
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
            'kind' => $this->kind,
            'stage' => $this->stage,
            'stage_complete' => $this->stageComplete,
            'finished' => $this->finished,
            'from_day_index' => $this->fromDayIndex,
        ];
    }
}
