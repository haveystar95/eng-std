<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DateTimeImmutable;

/**
 * A term the session should present, with only the Learning-owned facts. Term content
 * (text, translations, example, audio) is hydrated separately from Vocabulary when the
 * session payload is assembled — this read model never crosses that boundary.
 */
final readonly class DueTermView
{
    public function __construct(
        public TermId $termId,
        public LearningState $state,
        public int $intervalDays,
        public ?DateTimeImmutable $dueAt,
        public int $reps = 0, // review counter, drives the ExerciseSelector's review-mode rotation
        // The acquisition ladder, orthogonal to `state` above: which rung the pair stands on, and
        // therefore which trainers it is admitted to. Defaults describe a term with NO progress row
        // — never shown, so it stands at the intro.
        public Acquisition $acquisition = Acquisition::New,
        public int $learningStep = 0,
    ) {}
}
