<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * One trainer's answer to "can THIS term's content build your card?" — the status, the machine
 * reason, and the sentence a human reads on the back-office screen.
 *
 * `gap` is null exactly when `status` is `ok`; every other status carries one. The explanation is
 * always present and always Russian, for the same reason {@see
 * \App\Modules\Learning\Domain\Service\ModePassport::reasonFor()} keeps its wording in Domain: the
 * sentence IS the rule stated in words, and a panel that re-phrased it would drift from the gate.
 */
final readonly class ModeContentVerdict
{
    public function __construct(
        public ExerciseMode $mode,
        public ContentStatus $status,
        public ?ContentGap $gap,
        public string $explanation,
    ) {}
}
