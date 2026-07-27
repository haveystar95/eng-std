<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

final class FakeTermExistenceReader implements TermExistenceReader
{
    /** @var array<string, true>|null null means "every id is known" */
    private ?array $known;

    /** @param list<TermId>|null $known */
    private function __construct(?array $known)
    {
        $this->known = $known === null
            ? null
            : array_fill_keys(array_map(static fn (TermId $id): string => $id->value, $known), true);
    }

    public static function knowingAll(): self
    {
        return new self(null);
    }

    /** @param list<TermId> $known */
    public static function knowing(array $known): self
    {
        return new self($known);
    }

    public function existing(array $termIds): array
    {
        if ($this->known === null) {
            return array_values($termIds);
        }

        return array_values(array_filter(
            $termIds,
            fn (TermId $id): bool => isset($this->known[$id->value]),
        ));
    }
}
