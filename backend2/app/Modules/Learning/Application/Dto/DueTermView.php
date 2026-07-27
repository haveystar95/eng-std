<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

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
    ) {}
}
