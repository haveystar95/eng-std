<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * ONE MOVE OF THE LEARNER (наряд CONV-1, п. 3): what they said, or that they asked the role to
 * repeat itself («Не понял», кадр 37-7), or that they let the turn go. An interruption is not here:
 * cutting the role's voice short happens on the phone and the server never hears about it.
 */
final readonly class TakeConversationTurn
{
    public function __construct(
        public ConversationId $conversationId,
        public TurnKind $kind,
        public string $heard,
        public UserId $actorId,
    ) {}
}
