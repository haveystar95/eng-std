<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

/**
 * One thing the server refused of a talk's role, as the journal of refusals keeps it (`conversation_rejections`, наряд
 * FIX-4 §§3, 6): the role's line it was about, the attempt of the model and its row in `model_calls`, what was refused
 * (`rejected_answer` | `dropped_opening`), why, and what makes it readable.
 */
final readonly class InspectedRejection
{
    /** @param array<string, mixed> $detail */
    public function __construct(
        public int $turnIndex,
        public int $attempt,
        public string $kind,
        public string $reason,
        public ?string $modelCallId,
        public array $detail,
    ) {}
}
