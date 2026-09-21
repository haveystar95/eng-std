<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationAgentReply;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\TurnSpeaker;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\ConversationUnavailable;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\Service\RoleLines;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
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
 *
 * THE ANSWER IS CHECKED BEFORE IT IS SAID (наряд CONV-2, пп. 1 и 4б; наряд BACK-TAILS-2 §9): a reply that says a
 * line of the learner, one that says the learner's last move back, or a rescue that says the rescued line again
 * ({@see RoleLines}), is not voiced — the move is asked for once more with the reason (`REDO`), and counted. Once,
 * because the learner is waiting. When the second answer does it too — or does not come — the sentence that does it is
 * cut out if the rest of the answer stands on its own (`…_cut`). Otherwise a learner line is said as it came (`…_kept`)
 * — the guard never makes a move worse than no guard, and never turns a reply into «Врач не отвечает» — and an echo
 * with nothing of the role's own around it gives way to the pack's neutral line (`…_neutral`): the learner's words said
 * back are no move of the role at all.
 *
 * WHICH TARGETS THE MOVE SAID is decided here too, once the role has answered it (наряд BACK-TAILS-2 §2,
 * {@see PhraseUse}): the code's rule over every target not said yet, and the role's own `phrases_used` as its second
 * support. The move is completed with them before it is written, and before the next intention is chosen — a phrase
 * just said is not offered again.
 */
final readonly class ConversationMoves
{
    public function __construct(
        private ConversationAgent $agent,
        private TurnSpeaker $voice,
        private ConversationRules $rules,
        private CheckCounters $counters,
        private Clock $clock,
        private LanguagePacks $packs,
        private PhraseUse $phrases = new PhraseUse,
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

        $target = $this->packs->for($plan->targetLang()->value);
        $request = $this->agent->request($plan, $talk, $material, $turn, $heard, $turnsLeft);
        $reply = $this->checked(
            $request, $this->agent->ask($request), $talk, $material, $turn, $heard,
            $target, $this->packs->for($plan->nativeLang()->value),
        );
        if ($turn === 'said') {
            $talk->creditMove($this->phrases->heardIn($heard, self::unsaid($talk, $material), $reply->phrasesUsed, $target));
        }

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
     * The role's answer as it will be said: this one when it is the role's own; otherwise the answer to the same move
     * asked once more with the reason — billed for both calls, because the learner waited for both. When that answer
     * says a learner line too (or does not come), the sentences that say one are cut out of it if the rest stands on its
     * own ({@see RoleLines::withoutLearnerLines()}); if nothing would be left, it is said as it is — and counted.
     */
    private function checked(
        ConversationAgentRequest $request,
        ConversationAgentReply $reply,
        Conversation $talk,
        ConversationMaterialView $material,
        string $turn,
        string $heard,
        LanguagePack $target,
        LanguagePack $native,
    ): ConversationAgentReply {
        $fault = $this->fault($reply, $talk, $material, $turn, $heard, $target);
        if ($fault === null) {
            return $reply;
        }
        $version = $reply->cost->promptVersion ?? '';
        $this->counters->recordCodes($version, [$fault['code']]);

        try {
            $answer = $this->agent->ask($request->redo($fault['reason'], $reply->replyTarget, $fault['line']))->billedWith($reply->cost);
        } catch (ConversationUnavailable) {
            $answer = $reply;
        }
        if ($answer !== $reply && $this->fault($answer, $talk, $material, $turn, $heard, $target) === null) {
            return $answer;
        }
        if ($fault['reason'] === RoleLines::REDO_LEARNER_LINE) {
            $cut = RoleLines::withoutLearnerLines($answer->replyTarget, $answer->replyNative, $material->learnerLines(), self::saidSoFar($talk));
            if ($cut !== null) {
                $this->counters->recordCodes($version, [RoleLines::CODE_LEARNER_LINE_CUT]);

                return $answer->saying($cut['target'], $cut['native']);
            }
        }
        if ($fault['reason'] === RoleLines::REDO_LEARNER_ECHO && RoleLines::echoIn($answer->replyTarget, $heard, $target, $this->phrases) !== null) {
            $cut = RoleLines::withoutEcho($answer->replyTarget, $answer->replyNative, $heard, $target, $this->phrases);
            if ($cut !== null) {
                $this->counters->recordCodes($version, [RoleLines::CODE_LEARNER_ECHO_CUT]);

                return $answer->saying($cut['target'], $cut['native']);
            }
            [$neutral, $translation] = [$target->neutralReply(), $native->neutralReply()];
            if ($neutral !== null && $translation !== null) {
                $this->counters->recordCodes($version, [RoleLines::CODE_LEARNER_ECHO_NEUTRAL]);

                return $answer->saying($neutral, $translation);
            }
        }
        $this->counters->recordCodes($version, [$fault['kept']]);

        return $answer;
    }

    /**
     * What is wrong with an answer, if anything: a line of the learner said as the role's own (every move), the learner's
     * last move said back (a move the learner said something on), or, on a rescue, the rescued line said again.
     *
     * @return array{reason: 'learner_line'|'learner_echo'|'same_words', line: string|null, code: string, kept: string}|null
     */
    private function fault(ConversationAgentReply $reply, Conversation $talk, ConversationMaterialView $material, string $turn, string $heard, LanguagePack $target): ?array
    {
        $line = RoleLines::learnerLineIn($reply->replyTarget, $material->learnerLines(), self::saidSoFar($talk));
        if ($line !== null) {
            return ['reason' => RoleLines::REDO_LEARNER_LINE, 'line' => $line, 'code' => RoleLines::CODE_LEARNER_LINE, 'kept' => RoleLines::CODE_LEARNER_LINE_KEPT];
        }
        if ($turn === 'said' && RoleLines::echoIn($reply->replyTarget, $heard, $target, $this->phrases) !== null) {
            return ['reason' => RoleLines::REDO_LEARNER_ECHO, 'line' => null, 'code' => RoleLines::CODE_LEARNER_ECHO, 'kept' => RoleLines::CODE_LEARNER_ECHO_KEPT];
        }
        if ($turn === 'rescue' && RoleLines::repeats($reply->replyTarget, $talk->lineBeforeLastMove())) {
            return ['reason' => RoleLines::REDO_SAME_WORDS, 'line' => null, 'code' => RoleLines::CODE_SAME_WORDS, 'kept' => RoleLines::CODE_SAME_WORDS_KEPT];
        }

        return null;
    }

    /**
     * The talk's targets not said so far — the only ones a move is read for (наряд BACK-TAILS-2 §2, п. д): what is said
     * stays said, and every target counts, whichever scene the move stands in.
     *
     * @return list<ConversationPhrase>
     */
    private static function unsaid(Conversation $talk, ConversationMaterialView $material): array
    {
        $heard = ConversationOutcomes::heard($talk);

        return array_values(array_filter($material->targets, static fn (ConversationPhrase $p): bool => ! isset($heard[$p->id()])));
    }

    /**
     * Everything the learner has said in the talk, the move being answered included (it is in the journal in memory by
     * now) — what an echo of theirs is read against.
     *
     * @return list<string>
     */
    private static function saidSoFar(Conversation $talk): array
    {
        $out = [];
        foreach ($talk->turns() as $turn) {
            if ($turn->isSpokenByLearner()) {
                $out[] = (string) $turn->textTarget;
            }
        }

        return $out;
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
