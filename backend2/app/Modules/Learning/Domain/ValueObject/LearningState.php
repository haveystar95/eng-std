<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The state machine a term's progress moves through:
 *   new ─► learning ─► review, with review ─► relearning ─► review on a lapse.
 *
 * `known` is a triage shortcut: the user swiped "I know this", so the term skips the
 * learning ladder entirely. It re-enters `learning` only if a scheduled verification
 * (see TriageVerificationPlanner) is failed. `known` is never scheduled by the SM-2
 * scheduler — it is not an SRS state, it is a self-assessment awaiting proof.
 */
enum LearningState: string
{
    case New = 'new';
    case Learning = 'learning';
    case Review = 'review';
    case Relearning = 'relearning';
    case Known = 'known';
}
