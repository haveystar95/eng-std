<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHOSE MOVE IT IS (наряд CONV-1, кадры 37-6…37-11). Half-realtime by turns: the agent speaks, then
 * it is the learner's move, then the agent answers what was actually said. There is no «thinking»
 * state on the wire — the client draws its own three dots while the request is in flight.
 */
enum ConversationState: string
{
    case AgentTurn = 'agent_turn';
    case YourTurn = 'your_turn';
    case Ended = 'ended';
}
