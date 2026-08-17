<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermDifficultyView;
use App\Modules\Vocabulary\Application\Query\TermDifficultyReader;

final class FakeTermDifficultyReader implements TermDifficultyReader
{
    /** @param array<string, TermDifficultyView> $byId keyed by term id; missing → unknown difficulty */
    public function __construct(private readonly array $byId = []) {}

    public function byIds(array $termIds): array
    {
        $out = [];
        foreach ($termIds as $id) {
            if (isset($this->byId[$id->value])) {
                $out[$id->value] = $this->byId[$id->value];
            }
        }

        return $out;
    }
}
