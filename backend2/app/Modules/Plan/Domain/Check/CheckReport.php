<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\Finding;

/**
 * What a run of the checks produced: the (possibly corrected) answer, every finding, and whether
 * a gate refused the answer altogether.
 *
 * @template T
 */
final readonly class CheckReport
{
    /**
     * @param  T  $answer
     * @param  list<Finding>  $findings
     */
    public function __construct(
        public mixed $answer,
        public array $findings,
        public bool $gated,
    ) {}

    /** @return list<array{check: string, mode: string, action: string, detail: string}> */
    public function findingsAsArray(): array
    {
        return array_map(static fn (Finding $f): array => $f->toArray(), $this->findings);
    }

    /**
     * The gated checks' names — what the retry message quotes.
     *
     * @return list<string>
     */
    public function gatedChecks(): array
    {
        $names = [];
        foreach ($this->findings as $finding) {
            if ($finding->action === CheckAction::Gated) {
                $names[$finding->check] = true;
            }
        }

        return array_keys($names);
    }
}
