<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\ConversationMoves;
use App\Modules\Plan\Application\Service\ConversationPassing;
use App\Modules\Plan\Application\Service\ConversationViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Exception\ConversationNotInDay;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationType;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * «НАЧАТЬ РАЗГОВОР» — and «Продолжить» and «Ещё раз», which are the same door (наряд CONV-1, п. 3).
 *
 * A day has ONE open talk. Asked again while one is open, this returns that talk as it stands — a
 * phone that came back from the background carries on instead of starting over. Asked with `again`
 * (кадр 37-12), it closes the open one as `replayed` and opens a new one: what was said stays said,
 * and the day keeps both journals.
 *
 * The role's opening line is written HERE, so the learner never sees an empty ribbon waiting for
 * somebody to speak first. The model call and the voice happen outside the transaction; the write
 * that follows is short.
 */
final readonly class StartConversationHandler
{
    public function __construct(
        private PlanAccess $access,
        private ConversationRepository $conversations,
        private ConversationPassing $passing,
        private ConversationMaterial $material,
        private ConversationMoves $moves,
        private ConversationViews $views,
        private ConversationRules $rules,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(StartConversation $command): ConversationView
    {
        $plan = $this->access->owned($command->planId, $command->actorId);
        $day = $plan->day($command->number);
        if ($day->status() !== DayStatus::InProgress) {
            throw PlanDayNotOpen::day($command->number, $day->status());
        }
        if (! $day->hasConversation()) {
            throw ConversationNotInDay::day($command->number, 'the day was dealt before the conversation existed');
        }

        $open = $this->conversations->openForDay($day->id());
        if ($open !== null && ! $command->again) {
            return $this->views->of($open, $this->material->for($plan, $day));
        }

        $material = $this->material->for($plan, $day);
        if ($material->checkpoints === []) {
            throw ConversationNotInDay::day($command->number, 'no scene of this day has a lesson');
        }

        $now = $this->clock->now();
        $type = ConversationType::forDay($day->type());
        $talk = Conversation::start(
            id: ConversationId::generate(),
            planId: $plan->id(),
            userId: $plan->userId(),
            dayId: $day->id(),
            dayNumber: $day->number(),
            type: $type,
            sceneIds: $material->sceneIds(),
            turnLimit: $this->rules->turnsFor($type),
            hintsEnabled: $command->hints,
            now: $now,
        );

        // Outside the transaction: the learner waits on a model and a vendor, and nothing of theirs is locked.
        $this->moves->open($plan, $talk, $material);

        $this->tx->run(function () use ($open, $talk, $now): void {
            if ($open !== null) {
                $open->end(ConversationEnd::Replayed, $now);
                $this->conversations->save($open);
            }
            $this->conversations->save($talk);
            $this->passing->mark($talk);
        });

        return $this->views->of($talk, $material);
    }
}
