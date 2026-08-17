<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermAnswerKeyView;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;

final class FakeTermAnswerKeyReader implements TermAnswerKeyReader
{
    /** @param array<string, list<string>> $accepted per-term accepted forms; default ['correct'] */
    public function __construct(private readonly array $accepted = []) {}

    public function byIds(array $termIds): array
    {
        $out = [];
        foreach ($termIds as $id) {
            $out[$id->value] = new TermAnswerKeyView($id->value, $this->accepted[$id->value] ?? ['correct'], false);
        }

        return $out;
    }
}
