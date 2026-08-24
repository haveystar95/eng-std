<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * A near-synonym supplied by another module — primitives only, like every other input on this
 * boundary. The LANGUAGE is not carried: a synonym is on the term's own studied side by definition,
 * so the writer reads it off the term rather than trusting a caller to repeat it. `source` is a
 * plain string mapped to {@see \App\Modules\Vocabulary\Domain\ValueObject\SynonymSource} inside
 * Vocabulary — Generation must not touch this module's Domain value objects.
 */
final readonly class TermSynonymInput
{
    public function __construct(
        public string $text,
        public string $source = 'auto',
    ) {}
}
