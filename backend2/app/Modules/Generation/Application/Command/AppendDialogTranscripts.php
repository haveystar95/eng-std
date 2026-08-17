<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\DialogTranscriptEvent;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Append a batch of transcript events to a dialog and get back the current target-word coverage. */
final readonly class AppendDialogTranscripts
{
    /** @param list<DialogTranscriptEvent> $events */
    public function __construct(
        public UserId $userId,
        public PracticeDialogId $dialogId,
        public array $events,
    ) {}
}
