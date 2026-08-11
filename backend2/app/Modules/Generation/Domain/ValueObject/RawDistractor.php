<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * A distractor exactly as the model proposed it — unvalidated on purpose. `errorType` is the raw
 * wire string, not the enum, because an unknown value is one of the things the validator rejects;
 * parsing it at the boundary would turn a rejectable row into an exception.
 */
final readonly class RawDistractor
{
    public function __construct(
        public string $sentence,
        public string $errorType,
        public string $errorSpan,
        public string $correction,
    ) {}
}
