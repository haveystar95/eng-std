<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One trainer's content verdict, flattened to primitives for a reader OUTSIDE this module.
 *
 * The Domain objects it comes from ({@see \App\Modules\Learning\Domain\ValueObject\ModeContentVerdict})
 * stay inside Learning — a back-office projection may not import another module's Domain, which is
 * the same boundary {@see \App\Modules\Learning\Application\Service\LadderStepResolver} holds.
 */
final readonly class ModeContentStatusView
{
    public function __construct(
        /** The wire name of the trainer, e.g. `pick_correct`. */
        public string $mode,
        /** `ok` | `blocked` | `pool_dependent`. */
        public string $status,
        /** Machine reason; null only when the status is `ok`. */
        public ?string $reason,
        /** The same thing in Russian, for the screen. */
        public string $explanation,
    ) {}
}
