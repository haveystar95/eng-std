<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The talk as it stands — what a phone that lost the connection reads to carry on from (кадр 37-10). */
final readonly class GetConversation
{
    public function __construct(public ConversationId $conversationId, public UserId $actorId) {}
}
