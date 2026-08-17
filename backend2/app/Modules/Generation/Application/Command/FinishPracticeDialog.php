<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Close a dialog: record its estimated spend and return a native-language recap + word coverage. */
final readonly class FinishPracticeDialog
{
    public function __construct(
        public UserId $userId,
        public PracticeDialogId $dialogId,
    ) {}
}
