<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Replace a term's usage example with a new one (the "New example" action). Examples are persisted
 * add-only and aren't hydrated into the aggregate, so replacement is a dedicated write on the
 * term's own examples rather than an aggregate mutation.
 */
final readonly class ReplaceTermExample
{
    public function __construct(
        public TermId $termId,
        public string $sentence,
        public ?string $sentenceTranslation,
    ) {}
}
