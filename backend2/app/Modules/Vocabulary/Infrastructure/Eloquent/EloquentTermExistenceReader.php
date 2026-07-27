<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

final class EloquentTermExistenceReader implements TermExistenceReader
{
    public function existing(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        /** @var list<string> $found */
        $found = TermModel::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        return array_map(static fn (string $id): TermId => TermId::fromString($id), $found);
    }
}
