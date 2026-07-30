<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * The difficulty signals another module needs about a term, without reaching into Vocabulary's
 * tables: its CEFR level (null = unknown) and whether it is a phrase (which changes how a
 * "too fast to have read it" latency threshold applies).
 */
final readonly class TermDifficultyView
{
    public function __construct(
        public string $termId,
        public ?string $cefr,
        public bool $isPhrase,
    ) {}
}
