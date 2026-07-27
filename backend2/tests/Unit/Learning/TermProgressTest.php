<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

it('starts a term as new with default ease and nothing due', function () {
    $progress = TermProgress::start(UserId::generate(), TermId::generate());

    expect($progress->state())->toBe(LearningState::New)
        ->and($progress->isNew())->toBeTrue()
        ->and($progress->easeFactor())->toBe(2.50)
        ->and($progress->intervalDays())->toBe(0)
        ->and($progress->reps())->toBe(0)
        ->and($progress->lapses())->toBe(0)
        ->and($progress->dueAt())->toBeNull()
        ->and($progress->lastReviewedAt())->toBeNull();
});
