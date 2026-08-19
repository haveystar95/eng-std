<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** One item, the checks it failed, and why — the evidence a human reads beside the counts. */
final readonly class CandidateVerdict
{
    /**
     * @param  list<CheckId>  $failed  in the order the checks are declared
     * @param  array<string, list<string>>  $notes  check value → what exactly was wrong, for the report
     */
    public function __construct(
        public CandidateItem $item,
        public array $failed,
        public array $notes = [],
    ) {}

    public function isClean(): bool
    {
        return $this->failed === [];
    }

    public function failed(CheckId $check): bool
    {
        return in_array($check, $this->failed, true);
    }

    /** Every note flattened into one line — a table cell, not a paragraph. */
    public function reason(): string
    {
        $parts = [];
        foreach ($this->notes as $check => $notes) {
            $parts[] = $check . ': ' . implode('; ', $notes);
        }

        return implode(' · ', $parts);
    }
}
