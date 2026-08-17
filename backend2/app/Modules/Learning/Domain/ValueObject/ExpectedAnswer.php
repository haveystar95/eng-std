<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use InvalidArgumentException;

/**
 * The answer key for a card: every string that counts as correct (the target plus any accepted
 * synonyms/translations), whether the term is a phrase — phrases normalise punctuation and
 * spacing harder, and get a more forgiving default speed threshold — and how the comparison is
 * made ({@see MatchPolicy}).
 */
final readonly class ExpectedAnswer
{
    /** @var list<string> */
    public array $accepted;

    /** @param list<string> $accepted */
    public function __construct(
        array $accepted,
        public bool $isPhrase = false,
        public MatchPolicy $policy = MatchPolicy::Exact,
    ) {
        $accepted = array_values(array_filter($accepted, static fn (string $a): bool => trim($a) !== ''));
        if ($accepted === []) {
            throw new InvalidArgumentException('An expected answer needs at least one non-empty accepted string.');
        }
        $this->accepted = $accepted;
    }
}
