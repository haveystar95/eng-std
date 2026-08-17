<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * A stored pair plus its ladder rung — the row the progress screen draws.
 *
 * Composition rather than a second flat DTO with the same fourteen fields: the facts come from a
 * reporting read, the rung comes from the Learning module, and keeping them as two objects makes it
 * impossible to accidentally hand-write a rung into the reader.
 *
 * `step` is null when the pair is OUTSIDE the ladder (a triage «знаю»). Null is not step 0 — the
 * screen draws the dash for it, never the first dot.
 */
final readonly class AdminLadderPair
{
    public function __construct(
        public AdminLadderPairFacts $facts,
        public ?int $step,
    ) {}
}
