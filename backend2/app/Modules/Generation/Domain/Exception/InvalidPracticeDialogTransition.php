<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Generation\Domain\ValueObject\PracticeDialogStatus;
use DomainException;

final class InvalidPracticeDialogTransition extends DomainException
{
    public static function from(PracticeDialogStatus $current, PracticeDialogStatus $target): self
    {
        return new self("Cannot move a practice dialog from {$current->value} to {$target->value}.");
    }
}
