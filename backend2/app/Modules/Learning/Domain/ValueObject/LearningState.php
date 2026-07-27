<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The state machine a term's progress moves through:
 *   new ─► learning ─► review, with review ─► relearning ─► review on a lapse.
 */
enum LearningState: string
{
    case New = 'new';
    case Learning = 'learning';
    case Review = 'review';
    case Relearning = 'relearning';
}
