<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What a retro-audit found, split by the check that caught it and by what could be done about it.
 *
 * Fixed and deleted are counted separately per check on purpose: "the validator would reject 44 rows"
 * is a number that says nothing about cost, while "38 of them carried a repairable label and 6 broke
 * two places" says exactly what the content is worth.
 *
 * @phpstan-type CheckName 'equality'|'dedup'|'noop'|'circular'
 */
final readonly class DistractorAuditOutcome
{
    /**
     * @param  array<string, int>  $fixed  check → rows whose span/correction was derived and written
     * @param  array<string, int>  $deleted  check → rows deleted as unrepairable
     * @param  list<string>  $notes  human-readable line per touched row
     */
    public function __construct(
        public int $seen = 0,
        public array $fixed = [],
        public array $deleted = [],
        public array $notes = [],
    ) {}

    public function totalFixed(): int
    {
        return array_sum($this->fixed);
    }

    public function totalDeleted(): int
    {
        return array_sum($this->deleted);
    }
}
