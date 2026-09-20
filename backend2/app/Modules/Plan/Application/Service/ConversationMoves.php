<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationAgentReply;
use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Port\TurnSpeaker;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Plan\Domain\ValueObject\TurnCost;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * ONE MOVE OF THE ROLE, end to end (наряд CONV-1): ask the model, buy the voice, write the line,
 * offer the next intention, and close the talk when it is closed.
 *
 * It runs OUTSIDE any transaction — a turn waits on a model and a vendor, and nothing of the
 * learner's is locked while it does (the rule D-27 wrote for the slot judge). Nothing is written
 * anywhere until the role has answered: a move the model did not answer leaves the ribbon exactly
 * as it was, and the learner repeats it.
 *
 * THE MONEY CAP IS NOT A CUT-OFF. When the talk has spent what the plan allows it, the next move is
 * asked for with `TURNS_LEFT: 0` — which the prompt reads as «say goodbye now» — so the learner gets
 * a farewell in the role's own words and the talk ends `limit`. A learner is never left mid-sentence
 * because a number was reached.
 */
final readonly class ConversationMoves
{
    public function __construct(
        private ConversationAgent $agent,
        private TurnSpeaker $voice,
        private ConversationRules $rules,
        private Clock $clock,
    ) {}

    /** The line that opens the talk — the role speaks first, before the learner has said anything. */
    public function open(Plan $plan, Conversation $talk, ConversationMaterialView $material): void
    {
        $this->answer($plan, $talk, $material, 'start', '');
    }

    /**
     * The role's answer to the move that was just recorded.
     *
     * @param  'start'|'said'|'rescue'|'skip'  $turn
     */
    public function answer(Plan $plan, Conversation $talk, ConversationMaterialView $material, string $turn, string $heard): void
    {
        $capped = $talk->overCap($this->rules->costCapUsd);
        $turnsLeft = $capped ? 0 : $talk->turnsLeft();
        // Was the move BEFORE this one already off the scene? Then this one makes two in a row.
        $pushedAgain = $talk->offTopicStreak() > 0;

        $reply = $this->agent->move($plan, $talk, $material, $turn, $heard, $turnsLeft);

        $turnId = ConversationTurnId::generate();
        // The line is said in the voice of the scene it is said IN — so the role's voice changes with
        // the scene of a rehearsal, exactly as its name does in the scene strip (кадр 30-2b).
        $spoken = $this->voice->say(
            $talk->id(), $turnId, $plan->targetLang()->value, $reply->replyTarget,
            $material->checkpoint($talk->currentCheckpoint())->partnerGender ?? VoiceGender::Female,
        );
        /** @var TurnAudio|null $audio */
        $audio = $spoken['audio'];

        // The checkpoint the reply closes is marked BEFORE the hint is chosen: the intention offered
        // belongs to the scene the talk is now in, not to the one it has just finished.
        if ($reply->checkpointDone !== null) {
            $talk->markCheckpoint($reply->checkpointDone);
        }
        $speechCost = $audio->costUsd ?? '0.000000';
        $cost = new TurnCost(
            modelCostUsd: $reply->cost->modelCostUsd,
            speechCostUsd: $speechCost,
            modelLatencyMs: $reply->cost->modelLatencyMs,
            speechLatencyMs: (int) $spoken['latency_ms'],
            latencyMs: $reply->cost->modelLatencyMs + (int) $spoken['latency_ms'],
            model: $reply->cost->model,
            promptVersion: $reply->cost->promptVersion,
            tokensIn: $reply->cost->tokensIn,
            tokensOut: $reply->cost->tokensOut,
        );

        $now = $this->clock->now();
        $line = ConversationTurn::agent(
            id: $turnId,
            conversationId: $talk->id(),
            index: $talk->nextIndex(),
            textTarget: $reply->replyTarget,
            textNative: $reply->replyNative,
            audio: $audio,
            checkpointDone: $reply->checkpointDone,
            hintNative: $reply->endsTalk() ? null : $this->hintFor($talk, $material, $reply),
            cost: $cost,
            now: $now,
            understood: $reply->understood,
            phrasesUsed: [],
            offTopic: $reply->offTopic,
        );

        $talk->recordAgentTurn($line);
        $talk->spend($cost->totalUsd());

        $reason = self::endOf($reply, $turnsLeft, $capped, $pushedAgain && $reply->offTopic);
        if ($reason !== null) {
            $talk->end($reason, $now);
        }
    }

    /**
     * WHY THE TALK IS OVER, if it is. The role says so itself in its answer; the server only names
     * the money when the money is what made it say so, closes a talk the role forgot to close after
     * its last turn, and closes one the learner pushed off the scene twice in a row.
     */
    private static function endOf(ConversationAgentReply $reply, int $turnsLeft, bool $capped, bool $pushedTwice): ?ConversationEnd
    {
        // The role should say goodbye itself on the second push; when a `mini` model does not, the
        // server closes the talk anyway — a refused subject is not a matter of the model's mood.
        if ($reply->isDeclined() || $pushedTwice) {
            return ConversationEnd::Declined;
        }
        if ($reply->endsTalk() || $turnsLeft <= 0) {
            return $capped ? ConversationEnd::Limit : ConversationEnd::Natural;
        }

        return null;
    }

    /**
     * THE INTENTION OFFERED FOR THE NEXT MOVE (кадр 37-7, чип «Скажи, что …»).
     *
     * It is the native text of the learner's own line at the nearest checkpoint — the first line of
     * the scene the talk is in whose phrase has not sounded yet. That is the server's, not the
     * model's: the hint is what the learner CAME to say, and the lesson knows it exactly. The
     * model's `next_hint_native` is the fallback for a talk that has walked past its lines.
     *
     * In «Без подсказок» nothing is offered at all — the chip and the button do not exist there,
     * and a hint stored for a talk that will never show one is a hint paid for twice.
     */
    private function hintFor(Conversation $talk, ConversationMaterialView $material, ConversationAgentReply $reply): ?string
    {
        if (! $talk->hintsEnabled()) {
            return null;
        }
        $used = [];
        foreach ($talk->turns() as $turn) {
            foreach ($turn->phrasesUsed as $id) {
                $used[$id] = true;
            }
        }
        $checkpoint = $material->checkpoint($talk->currentCheckpoint());
        if ($checkpoint === null) {
            return $reply->nextHintNative;
        }
        foreach ($checkpoint->keyLines as $line) {
            $ref = $line['phrase_ref'];
            if ($ref === null || isset($used[$checkpoint->sceneId.':'.$ref])) {
                continue;
            }

            return $line['native'];
        }

        return $reply->nextHintNative;
    }
}
