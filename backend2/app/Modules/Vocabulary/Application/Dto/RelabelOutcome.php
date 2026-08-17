<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * What a relabel pass decided. `kept` matters as much as `relabelled`: this command's whole risk is
 * mislabelling a legitimately foreign-language row as the learner's, so what it REFUSED to touch,
 * and why, is part of the result rather than a debug detail.
 */
final readonly class RelabelOutcome
{
    /**
     * @param  list<array{row_id: string, term: string, from: string, text: string}>  $relabelled
     * @param  list<array{row_id: string, term: string, lang: string, text: string, why: string}>  $kept
     */
    public function __construct(
        public array $relabelled,
        public array $kept,
        public bool $applied,
    ) {}

    public function relabelledCount(): int
    {
        return count($this->relabelled);
    }

    public function keptCount(): int
    {
        return count($this->kept);
    }
}
