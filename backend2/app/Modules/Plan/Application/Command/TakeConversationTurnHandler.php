<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\ConversationMoves;
use App\Modules\Plan\Application\Service\ConversationPassing;
use App\Modules\Plan\Application\Service\ConversationViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Exception\ConversationEnded;
use App\Modules\Plan\Domain\Exception\ConversationNotFound;
use App\Modules\Plan\Domain\Exception\ConversationNotYourTurn;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * ONE MOVE OF THE TALK (наряд CONV-1, п. 3): the learner's line, the role's answer to it, and what
 * the server itself decided about both.
 *
 * WHAT IS THE SERVER'S AND WHAT IS THE MODEL'S. Which constructions of its scene the move said — or said almost — is the
 * server's judge alone ({@see \App\Modules\Plan\Domain\Service\FrameJudge}, наряд FIX-4 §2), and the move is judged
 * before the role is asked, so it is written whole and the role answering it is told what it said. «Понял ли вопрос» is
 * the model's, because that is a judgement about meaning and only the role was asked it. The scene the move is made in
 * is the talk's current one, and it travels with the line.
 *
 * THE ORDER, and why it is this one: the move is checked, the model and the voice are called with
 * nothing locked, and only then is the row locked, re-checked and written (the pattern
 * {@see JudgeCardHandler} uses). A second move sent while the first is still in flight finds the
 * talk no longer waiting for the learner and is refused — it does not buy a second reply.
 *
 * A model that did not answer writes NOTHING: the ribbon stays where it was and the learner repeats
 * the move (кадр 37-10). That is why the learner's own line is only recorded inside the aggregate in
 * memory until the role has answered.
 *
 * A rescue carries the words the learner's bubble shows — «Sorry?» in the language of the talk (наряд
 * CONV-2, п. 4а) — and a talk that comes to an end of its own here walks the day's sixth stage, written
 * in the journal of stages in the same transaction ({@see ConversationPassing}, п. 2).
 */
final readonly class TakeConversationTurnHandler
{
    public function __construct(
        private PlanAccess $access,
        private ConversationRepository $conversations,
        private ConversationPassing $passing,
        private ConversationMaterial $material,
        private ConversationMoves $moves,
        private ConversationViews $views,
        private LanguagePacks $packs,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(TakeConversationTurn $command): ConversationView
    {
        $talk = $this->conversations->find($command->conversationId, $command->actorId);
        if ($talk === null) {
            throw ConversationNotFound::id($command->conversationId->value);
        }
        if ($talk->isEnded()) {
            throw ConversationEnded::talk($talk->id());
        }
        if ($talk->state() !== ConversationState::YourTurn) {
            throw ConversationNotYourTurn::state($talk->state());
        }

        $plan = $this->access->owned($talk->planId(), $command->actorId);
        $day = $plan->day($talk->dayNumber());
        $material = $this->material->for($plan, $day);

        $heard = trim($command->heard);
        $pack = $this->packs->for($plan->targetLang()->value);

        $before = count($talk->turns());
        ['scene' => $scene, 'verdict' => $verdict] = $this->moves->judged($plan, $talk, $material, $command->kind, $heard);
        $talk->recordLearnerTurn(ConversationTurn::learner(
            id: ConversationTurnId::generate(),
            conversationId: $talk->id(),
            index: $talk->nextIndex(),
            kind: $command->kind,
            heard: match ($command->kind) {
                TurnKind::Said => $heard,
                TurnKind::Rescue => $pack->rescueLine(),
                default => null,
            },
            phrasesUsed: $verdict->said,
            now: $this->clock->now(),
            phrasesAlmost: $verdict->almost,
            sceneId: $scene,
        ));

        // The model and the voice: outside every transaction, nothing of the learner's locked.
        $this->moves->answer($plan, $talk, $material, $command->kind->promptTurn(), $heard);

        $this->tx->run(function () use ($talk, $before): void {
            $held = $this->conversations->lockState($talk->id());
            if ($held === null || $held['turns'] !== $before || $held['state'] !== ConversationState::YourTurn) {
                throw ConversationNotYourTurn::state($held['state'] ?? ConversationState::Ended);
            }
            $this->conversations->save($talk);
            $this->passing->mark($talk);
        });

        return $this->views->of($talk, $material);
    }
}
