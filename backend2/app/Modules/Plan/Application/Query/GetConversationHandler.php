<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\ConversationViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Exception\ConversationNotFound;
use App\Modules\Plan\Domain\Repository\ConversationRepository;

/**
 * THE TALK AS IT STANDS — the door back in after a dropped connection (кадр 37-10, «Связь пропала —
 * разговор продолжится отсюда»). The same document a move returns, so a phone that reconnects draws
 * the ribbon from one shape and not from two.
 */
final readonly class GetConversationHandler
{
    public function __construct(
        private PlanAccess $access,
        private ConversationRepository $conversations,
        private ConversationMaterial $material,
        private ConversationViews $views,
    ) {}

    public function __invoke(GetConversation $query): ConversationView
    {
        $talk = $this->conversations->find($query->conversationId, $query->actorId);
        if ($talk === null) {
            throw ConversationNotFound::id($query->conversationId->value);
        }
        $plan = $this->access->owned($talk->planId(), $query->actorId);

        return $this->views->of($talk, $this->material->for($plan, $plan->day($talk->dayNumber())));
    }
}
