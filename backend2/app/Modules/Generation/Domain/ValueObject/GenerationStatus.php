<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** Lifecycle of one generation: pending → running → (succeeded | failed). Terminal states are final. */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
