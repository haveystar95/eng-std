<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** An alternative CORRECT answer as the model proposed it, with its rationale. Unvalidated. */
final readonly class RawVariant
{
    public function __construct(
        public string $text,
        public ?string $note,
    ) {}
}
