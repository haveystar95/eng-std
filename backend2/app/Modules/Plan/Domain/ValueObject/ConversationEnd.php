<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHY THE TALK IS OVER (наряд CONV-1):
 *
 * - `natural` — the agent said goodbye when its checkpoints were walked or its turns ran out;
 * - `limit` — the talk reached the money the plan allows it (`plan.conversation.cost_cap_usd`) and
 *   the agent was told to close it on its next move; the learner is never cut off mid-word;
 * - `declined` — the learner pushed a forbidden subject twice and the role said goodbye;
 * - `replayed` — «Ещё раз»: a new talk was started for the day and this one was closed to make room
 *   (one open talk per day).
 */
enum ConversationEnd: string
{
    case Natural = 'natural';
    case Limit = 'limit';
    case Declined = 'declined';
    case Replayed = 'replayed';
}
