<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One thing wrong with a model answer, as a CODE plus enough detail to fix it.
 *
 * A code and not a sentence, because the two readers want different things. A regeneration decides
 * on the code — this class of failure is worth paying to retry, that one is not — and a person
 * reading `fail_reason` at 8am wants the sentence. Both from one row.
 */
final readonly class PlanViolation
{
    public function __construct(
        public string $code,
        public string $detail,
        /** Which card it is about, where that is meaningful. */
        public ?string $subject = null,
    ) {}

    public function __toString(): string
    {
        return $this->subject === null
            ? "{$this->code}: {$this->detail}"
            : "{$this->code} [{$this->subject}]: {$this->detail}";
    }
}
