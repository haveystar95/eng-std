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
        // The ladder's own counter — correct non-practice reviews since graduation. Rungs 3–5 are
        // read off THIS, not off `reps` above.
        public int $successfulReviews = 0,
        // Is this pair in the learner's POOL (`enrolled_at` non-null)? True for every selection but
        // one: a collection's FREE PRACTICE drills the whole collection, so it also carries the
        // words nobody has triaged. They are not a rung and must not be dealt one — see
        // {@see \App\Modules\Learning\Domain\Service\LearningLadder::STEP_UNENROLLED_PRACTICE}.
        public bool $inPool = true,
    ) {}

    /**
     * A term of the collection with no progress row at all — never met, never chosen. The ordinary
     * state of an untriaged collection, and the reason this is a named constructor rather than a
     * pile of defaults at every call site: «what does a word we know nothing about look like» is
     * one answer, given once.
     */
    public static function outOfPool(TermId $termId): self
    {
        return new self($termId, LearningState::New, 0, null, inPool: false);
    }
}
