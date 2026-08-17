<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What a repair pass found and what it cost. Returned rather than logged so the console command can
 * print it and a test can assert it — a pass that spends money should be able to say how much
 * before and after.
 */
final readonly class EchoExampleRepairReport
{
    /**
     * @param  list<string>  $repairedTermIds
     * @param  list<array{term_id: string, error: string}>  $failures  terms whose call did not land
     */
    public function __construct(
        public int $examined,
        public int $needingRepair,
        public array $repairedTermIds = [],
        public array $failures = [],
        public int $tokensIn = 0,
        public int $tokensOut = 0,
        public string $costUsd = '0.000000',
    ) {}

    public function repaired(): int
    {
        return count($this->repairedTermIds);
    }
}
