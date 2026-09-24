<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\ConversationMoves;
use App\Modules\Plan\Application\Service\ConversationPassing;
use App\Modules\Plan\Application\Service\ConversationViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Exception\ConversationNotInDay;
use App\Modules\Plan\Domain\Exception\ConversationReplayLimit;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationType;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\Stage;
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
 *
 * «ПОВТОРИТЬ РАЗГОВОР» (наряд BACK-TAILS-2 §7): once the day's sixth stage is walked, a new talk is a REPLAY — on a day
 * still being walked and on a passed one alike (the window offers it as the talk row's `again`, наряд FIX-3 §8). A replay changes neither the day's
 * state nor its result: the walked talk stays the day's, the closed day stays closed, and a replay that ends of its own
 * walks nothing ({@see ConversationPassing}). Each replay is a model and a voice paid for, so a day of the plan takes
 * `plan.conversation.replays_per_day` of them per calendar day of the learner — past that, 409
 * `plan_conversation_replay_limit` with the learner's next midnight in `retry_after_utc`, and nothing is started.
 */
final readonly class StartConversationHandler
{
    public function __construct(
        private PlanAccess $access,
        private ConversationRepository $conversations,
        private StagePassageRepository $passages,
        private ConversationPassing $passing,
        private ConversationMaterial $material,
        private ConversationMoves $moves,
        private ConversationViews $views,
        private ConversationRules $rules,
        private LearnerCalendar $calendar,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(StartConversation $command): ConversationView
    {
        $plan = $this->access->owned($command->planId, $command->actorId);
        $day = $plan->day($command->number);
        $walked = $this->passages->of($day->id(), Stage::Conversation);
        $replayable = $walked !== null && $day->status() === DayStatus::Closed;
        if ($day->status() !== DayStatus::InProgress && ! $replayable) {
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
        if ($walked !== null && $walked->conversationId !== null) {
            $midnight = $this->calendar->todayFor($plan->userId(), $now);
            $replays = $this->conversations->replaysSince($day->id(), $walked->conversationId, $walked->passedAt, $midnight);
            if ($replays >= $this->rules->replaysPerDay) {
                throw ConversationReplayLimit::day($command->number, $this->rules->replaysPerDay, $midnight->modify('+1 day'));
            }
        }
        $type = ConversationType::forDay($day->type());
        $talk = Conversation::start(
            id: ConversationId::generate(),
            planId: $plan->id(),
            userId: $plan->userId(),
            dayId: $day->id(),
            dayNumber: $day->number(),
            type: $type,
            sceneIds: $material->sceneIds(),
            // One move per target and two more (наряд FIX-3 §7); over several scenes, one more in each (FIX-4 §4).
            turnLimit: $material->walksScenes()
                ? $this->rules->turnsForScenes(array_map(static fn (string $scene): int => count($material->targetsOf($scene)), $material->sceneIds()))
                : $this->rules->turnsFor(count($material->targets)),
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
