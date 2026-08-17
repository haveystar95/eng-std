<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** Who spoke a transcript line. Only the user's lines count toward target-word coverage. */
enum TranscriptRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function isUser(): bool
    {
        return $this === self::User;
    }
}
