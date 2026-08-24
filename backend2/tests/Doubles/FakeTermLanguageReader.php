<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;

/**
 * Every term speaks the same language unless the test says otherwise — which is what a unit test of
 * anything OTHER than the pair invariant wants: the gate passes and stays out of the way.
 */
final class FakeTermLanguageReader implements TermLanguageReader
{
    /** @param array<string, string> $overrides term id => language */
    public function __construct(
        private readonly string $defaultLang = 'en',
        private readonly array $overrides = [],
    ) {}

    public function langsFor(array $termIds): array
    {
        $out = [];
        foreach ($termIds as $termId) {
            $out[$termId->value] = $this->overrides[$termId->value] ?? $this->defaultLang;
        }

        return $out;
    }
}
