<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * What slice of a learner's pairs the progress table is showing.
 *
 * `phase` is one of `new` | `learning` | `graduated` | `known`, matching the four counters. Null is
 * "every phase". Unknown values are rejected at the HTTP edge, never silently ignored — a filter
 * that quietly does nothing is how a screen ends up believed for a slice it never applied.
 *
 * `inPool` is the question the pool made askable: is this pair one the learner is actually studying
 * (`true`), or one that has a row and sits outside the queue (`false` — a «знаю» self-assessment, or
 * a word paused with «Убрать из изучения»)? Null is «both», the default, because the table is a
 * record of everything the app knows about the learner and narrowing it by default would hide the
 * very rows someone opens this screen to explain.
 */
final readonly class AdminLadderFilter
{
    public const PHASES = ['new', 'learning', 'graduated', 'known'];

    public function __construct(
        public ?string $collectionId = null,
        public ?string $phase = null,
        public bool $dueOnly = false,
        public ?bool $inPool = null,
    ) {}
}
