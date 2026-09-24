<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHAT THE SERVER REFUSED OF A MOVE OF THE ROLE (наряд FIX-4 §§3, 6): `rejected_answer` — an answer a guard would not let
 * be said (a learner's line, an echo, the same line again, the talk closed with moves left), asked for once more;
 * `dropped_opening` — the door the answer said it opened, which is no door of the scene: a target of another scene, one
 * already said, an id the talk does not have.
 */
enum RejectionKind: string
{
    case RejectedAnswer = 'rejected_answer';
    case DroppedOpening = 'dropped_opening';
}
