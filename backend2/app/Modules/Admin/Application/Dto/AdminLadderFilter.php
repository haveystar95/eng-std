<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * What slice of a learner's pairs the progress table is showing.
 *
 * `phase` is one of `new` | `learning` | `graduated` | `known`, matching the four counters. Null is
 * "every phase". Unknown values are rejected at the HTTP edge, never silently ignored — a filter
 * that quietly does nothing is how a screen ends up believed for a slice it never applied.
 */
final readonly class AdminLadderFilter
{
    public const PHASES = ['new', 'learning', 'graduated', 'known'];

    public function __construct(
        public ?string $collectionId = null,
        public ?string $phase = null,
        public bool $dueOnly = false,
    ) {}
}
