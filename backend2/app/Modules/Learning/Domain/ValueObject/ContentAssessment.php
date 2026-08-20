<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * Everything one term's CONTENT decides, in one object: how many distractors it really has, and
 * what each trainer can do with it.
 *
 * `usableDistractors` travels beside the verdicts rather than being re-derived by the caller,
 * because it is the same number the pick_correct verdict was computed from — a report that counted
 * the raw rows would show «3 дистрактора» next to «pick_correct: не собирается» and read as a bug
 * in the trainer.
 */
final readonly class ContentAssessment
{
    /** @param  array<string, ModeContentVerdict>  $modes  keyed by {@see ExerciseMode::$value} */
    public function __construct(
        public int $usableDistractors,
        public array $modes,
    ) {}

    public function for(ExerciseMode $mode): ModeContentVerdict
    {
        return $this->modes[$mode->value];
    }

    public function supports(ExerciseMode $mode): bool
    {
        return $this->for($mode)->status === ContentStatus::Ok;
    }
}
