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
use App\Modules\Plan\Domain\Service\ConversationLead;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\Service\LineShare;
use App\Modules\Plan\Domain\Service\RoleLines;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\ConversationRejection;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;
use App\Modules\Plan\Domain\ValueObject\RejectionKind;
use App\Modules\Plan\Domain\ValueObject\SceneEvent;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Plan\Domain\ValueObject\TurnCost;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * THE ROLE'S LINES, end to end (наряд CONV-1): ask the model, buy the voice, write the line, offer the next intention,
 * and close the talk when it is closed.
 *
 * It runs OUTSIDE any transaction — a turn waits on a model and a vendor, and nothing of the
 * learner's is locked while it does (the rule D-27 wrote for the slot judge). Nothing is written
 * anywhere until the role has answered: a move the model did not answer leaves the ribbon exactly
 * as it was, and the learner repeats it — the goodbye of a scene and the next role's greeting too: both, or neither.
 *
 * THE MOVE IS JUDGED BEFORE THE ROLE IS ASKED (наряд FIX-4 §2, {@see judged()}): the constructions of the scene the
 * talk is in, not said yet, by the code alone — so the role answering it is told what it said, and the move is written
 * whole. The model's own opinion of the constructions is not asked for.
 *
 * THE SCENES ARE THE SERVER'S (наряд FIX-4 §4). In a talk over several scenes a scene is over when every target of it is
 * said or its moves are spent (targets + 1): the role's next line is its goodbye (`scene_event: end`, the checkpoint
 * marked on it, no door), and the next scene's role greets the learner at once and opens the first door of its scene
 * (`start`, its own voice). The goodbye of the last scene ends the talk. When the talk's own moves (or its money, or its
 * minutes) run out first, the role's last line is a goodbye all the same — the scene it cut marked by no checkpoint, the
 * scenes after it not walked, which is what the talk's `ended_by_limit` reads. A day's talk has one scene and no borders:
 * its lines carry the scene, and it ends on its moves as before.
 *
 * THE CAPS ARE NOT A CUT-OFF. When the talk has spent what the plan allows it — the money, or the minutes of its kind
 * (наряд FIX-3 §7: day 5, rehearsal 6, review 4) — the next move is asked for with `TURNS_LEFT: 0`, which the prompt
 * reads as «say goodbye now», so the learner gets a farewell in the role's own words and the talk ends `limit`. A
 * learner is never left mid-sentence because a number was reached.
 *
 * THE ANSWER IS CHECKED BEFORE IT IS SAID (наряд CONV-2, пп. 1 и 4б; наряд BACK-TAILS-2 §9): a reply that says a
 * line of the learner, one that says a move of the learner back (any move of the talk — наряд FIX-3 §11), a rescue
 * that says the rescued line again, a line of its own said again, a talk closed with moves left
 * ({@see RoleLines}) — is not voiced: the move is asked for once more with the reason (`REDO`), and counted. Once,
 * because the learner is waiting. When the second answer does it too — or does not come — the sentence that does it is
 * cut out if the rest of the answer stands on its own (`…_cut`). Otherwise a learner line is said as it came (`…_kept`)
 * — the guard never makes a move worse than no guard, and never turns a reply into «Врач не отвечает» — and an echo
 * with nothing of the role's own around it gives way to the pack's neutral line (`…_neutral`). Every refused answer is
 * journaled with its attempt and its call (наряд FIX-4 §6), and so is a door the role named that is no door of its scene
 * (§3: another scene's target, one said, an id the talk does not have) — the door is dropped, the line stays.
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
        private FrameJudge $judge = new FrameJudge,
        private LineShare $lines = new LineShare,
    ) {}

    /**
     * THE LEARNER'S MOVE AS THE SERVER HEARS IT (наряд FIX-4 §2): the scene the talk is in, and what the move says of the
     * constructions of that scene not said yet — its targets and its other frames alike. Only a move with words is judged.
     *
     * @return array{scene: string|null, verdict: MoveVerdict}
     */
    public function judged(Plan $plan, Conversation $talk, ConversationMaterialView $material, TurnKind $kind, string $heard): array
    {
        $scene = $material->checkpoint($talk->currentCheckpoint())?->sceneId;
        if ($scene === null || ! $kind->isJudged() || trim($heard) === '') {
            return ['scene' => $scene, 'verdict' => new MoveVerdict];
        }
        $said = ConversationOutcomes::heard($talk);
        $frames = array_values(array_filter($material->phrasesOf($scene), static fn (ConversationPhrase $p): bool => ! isset($said[$p->id()])));

        return ['scene' => $scene, 'verdict' => $this->judge->move($heard, $frames, $this->packs->for($plan->targetLang()->value))];
    }

    /** The line that opens the talk — the role of its first scene speaks first, before the learner has said anything. */
    public function open(Plan $plan, Conversation $talk, ConversationMaterialView $material): void
    {
        $scene = $material->checkpoint($talk->currentCheckpoint());
        if ($scene !== null) {
            $this->greet($plan, $talk, $material, $scene);
        }
    }

    /**
     * The role's answer to the move that was just recorded — and, when that move closed its scene, the scene's goodbye
     * and the next role's greeting.
     *
     * @param  'start'|'said'|'rescue'|'skip'  $turn
     */
    public function answer(Plan $plan, Conversation $talk, ConversationMaterialView $material, string $turn, string $heard): void
    {
        $scene = $material->checkpoint($talk->currentCheckpoint());
        if ($scene === null) {
            return;
        }
        $capped = $talk->overCap($this->rules->costCapUsd) || $talk->activeSeconds() >= $this->rules->secondsFor($talk->type());
        $turnsLeft = $capped ? 0 : $talk->turnsLeft();

        if (! $material->walksScenes()) {
            $this->reply($plan, $talk, $material, $scene, $turn, $heard, $turnsLeft, $capped);

            return;
        }
        $over = $turn !== 'rescue' && $this->sceneOver($talk, $material, $scene);
        if (! $over && $turnsLeft > 0) {
            $this->reply($plan, $talk, $material, $scene, $turn, $heard, $turnsLeft, $capped);

            return;
        }
        $next = $material->sceneAfter($scene->sceneId);
        $final = $turnsLeft <= 0 || $next === null;
        $this->farewell($plan, $talk, $material, $scene, $turn, $heard, $final ? 0 : $turnsLeft, $over);
        if ($final) {
            $talk->end($capped ? ConversationEnd::Limit : ConversationEnd::Natural, $this->clock->now());

            return;
        }
        $this->greet($plan, $talk, $material, $next);
    }

    /**
     * IS THE SCENE OVER (наряд FIX-4 §4): every target of it said, or the learner's moves in it spent — one per target
     * and one more.
     */
    private function sceneOver(Conversation $talk, ConversationMaterialView $material, ConversationCheckpoint $scene): bool
    {
        $targets = $material->targetsOf($scene->sceneId);
        $said = ConversationOutcomes::heard($talk);
        $unsaid = array_filter($targets, static fn (ConversationPhrase $t): bool => ! isset($said[$t->id()]));

        return ($targets !== [] && $unsaid === [])
            || $talk->movesIn($scene->sceneId) >= $this->rules->sceneTurnsFor(count($targets));
    }

    /**
     * An answer of the role inside its scene — the talk's last one when its moves are spent.
     *
     * @param  'start'|'said'|'rescue'|'skip'  $turn
     */
    private function reply(Plan $plan, Conversation $talk, ConversationMaterialView $material, ConversationCheckpoint $scene, string $turn, string $heard, int $turnsLeft, bool $capped): void
    {
        // Was the move BEFORE this one already off the scene? Then this one makes two in a row.
        $pushedAgain = $talk->offTopicStreak() > 0;
        $said = ConversationOutcomes::heard($talk);
        $almost = $turn === 'said' ? ($talk->lastTurn()->phrasesAlmost ?? []) : [];
        $targets = $material->targetsOf($scene->sceneId);
        $target = $this->packs->for($plan->targetLang()->value);

        $request = $this->agent->request(
            $plan, $talk, $material, $scene, $turn, $heard, $turnsLeft, $said, ConversationLead::next($talk, $targets, $said, $almost),
        );
        $index = $talk->nextIndex();
        [$reply, $attempt] = $this->checked($request, $this->agent->ask($request), $talk, $material, $turn, $target, $this->packs->for($plan->nativeLang()->value), $turnsLeft, $index, true);
        // A move that broke off is not a move misunderstood — whatever the role judged (наряд FIX-3 §7).
        if ($turn === 'said' && $reply->understood === false && $this->judge->breaksOff($heard, $material->phrasesOf($scene->sceneId), $target)) {
            $reply = $reply->unjudged();
        }
        $reason = self::endOf($reply, $turnsLeft, $capped, $pushedAgain && $reply->offTopic);
        $opens = $reason === null ? $this->door($talk, $material, $scene, $reply, $said, $index, $attempt) : null;
        $hint = $reason === null ? ConversationLead::hint($targets, $said, $almost, $opens) : null;

        $this->write($plan, $talk, $scene, $reply, $index, [
            'opens' => $opens,
            'hint' => $hint,
            // A day's talk walked to its end walks its one scene; a talk over several marks each on its own goodbye.
            'checkpoint' => $reason === ConversationEnd::Natural && ! $material->walksScenes() ? $scene->sceneId : null,
            'event' => null,
        ]);
        if ($reason !== null) {
            $talk->end($reason, $this->clock->now());
        }
    }

    /**
     * THE GOODBYE OF A SCENE (наряд FIX-4 §4): the role reacts to the move in a few words and says goodbye in its scene —
     * no door, no hint. Its checkpoint is marked here when the scene closed by its own rule; a goodbye the talk's limit
     * forced marks none.
     *
     * @param  'start'|'said'|'rescue'|'skip'  $turn
     */
    private function farewell(Plan $plan, Conversation $talk, ConversationMaterialView $material, ConversationCheckpoint $scene, string $turn, string $heard, int $turnsLeft, bool $over): void
    {
        $said = ConversationOutcomes::heard($talk);
        $target = $this->packs->for($plan->targetLang()->value);
        $request = $this->agent->request($plan, $talk, $material, $scene, $turn, $heard, $turnsLeft, $said, null, sceneEnd: true);
        $index = $talk->nextIndex();
        [$reply] = $this->checked($request, $this->agent->ask($request), $talk, $material, $turn, $target, $this->packs->for($plan->nativeLang()->value), $turnsLeft, $index, false);
        if ($turn === 'said' && $reply->understood === false && $this->judge->breaksOff($heard, $material->phrasesOf($scene->sceneId), $target)) {
            $reply = $reply->unjudged();
        }

        $this->write($plan, $talk, $scene, $reply, $index, [
            'opens' => null,
            'hint' => null,
            'checkpoint' => $over ? $scene->sceneId : null,
            'event' => SceneEvent::End,
        ]);
    }

    /**
     * THE ROLE OF A SCENE SPEAKS FIRST (наряд FIX-4 §4): the talk's opening line, or a new role meeting the learner after
     * the one before said goodbye — it greets them as this person and opens the first door of its scene.
     */
    private function greet(Plan $plan, Conversation $talk, ConversationMaterialView $material, ConversationCheckpoint $scene): void
    {
        $said = ConversationOutcomes::heard($talk);
        $targets = $material->targetsOf($scene->sceneId);
        $turnsLeft = $talk->turnsLeft();
        $request = $this->agent->request($plan, $talk, $material, $scene, 'start', '', $turnsLeft, $said, ConversationLead::next($talk, $targets, $said));
        $index = $talk->nextIndex();
        [$reply, $attempt] = $this->checked(
            $request, $this->agent->ask($request), $talk, $material, 'start', $this->packs->for($plan->targetLang()->value),
            $this->packs->for($plan->nativeLang()->value), $turnsLeft, $index, true,
        );
        $opens = $this->door($talk, $material, $scene, $reply, $said, $index, $attempt);

        $this->write($plan, $talk, $scene, $reply->unjudged(), $index, [
            'opens' => $opens,
            'hint' => ConversationLead::hint($targets, $said, [], $opens),
            'checkpoint' => null,
            'event' => $material->walksScenes() ? SceneEvent::Start : null,
        ]);
    }

    /**
     * The line written: said in the voice of the scene it is said IN (so the voice changes with the scene's role, on its
     * greeting), billed, and offering the hint when hints are on.
     *
     * @param  array{opens: string|null, hint: array{target: ConversationPhrase, exact: bool}|null, checkpoint: string|null, event: SceneEvent|null}  $how
     */
    private function write(Plan $plan, Conversation $talk, ConversationCheckpoint $scene, ConversationAgentReply $reply, int $index, array $how): void
    {
        $turnId = ConversationTurnId::generate();
        $spoken = $this->voice->say($talk->id(), $turnId, $plan->targetLang()->value, $reply->replyTarget, $scene->partnerGender);
        /** @var TurnAudio|null $audio */
        $audio = $spoken['audio'];
        $cost = new TurnCost(
            modelCostUsd: $reply->cost->modelCostUsd,
            speechCostUsd: $audio->costUsd ?? '0.000000',
            modelLatencyMs: $reply->cost->modelLatencyMs,
            speechLatencyMs: (int) $spoken['latency_ms'],
            latencyMs: $reply->cost->modelLatencyMs + (int) $spoken['latency_ms'],
            model: $reply->cost->model,
            promptVersion: $reply->cost->promptVersion,
            tokensIn: $reply->cost->tokensIn,
            tokensOut: $reply->cost->tokensOut,
        );

        $talk->recordAgentTurn(ConversationTurn::agent(
            id: $turnId,
            conversationId: $talk->id(),
            index: $index,
            textTarget: $reply->replyTarget,
            textNative: $reply->replyNative,
            audio: $audio,
            checkpointDone: $how['checkpoint'],
            // In «Без подсказок» nothing is offered: the chip does not exist there.
            hintNative: $talk->hintsEnabled() && $how['hint'] !== null ? $how['hint']['target']->lineNative : null,
            cost: $cost,
            now: $this->clock->now(),
            understood: $reply->understood,
            offTopic: $reply->offTopic,
            opensTarget: $how['opens'],
            sceneId: $scene->sceneId,
            sceneEvent: $how['event'],
        ));
        $talk->spend($cost->totalUsd());
    }

    /**
     * THE DOOR THE LINE OPENS, as the server accepts it (наряд FIX-4 §3): the target the role named by its short id — of
     * the scene the line is said in, and not said yet (one said almost is welcome). Anything else is dropped and journaled
     * with why: another scene's target, one already said, an id the talk does not have.
     *
     * @param  array<string, true>  $said
     * @return string|null the target's id (`<scene>:<ref>`)
     */
    private function door(Conversation $talk, ConversationMaterialView $material, ConversationCheckpoint $scene, ConversationAgentReply $reply, array $said, int $index, int $attempt): ?string
    {
        if ($reply->opens === null) {
            return null;
        }
        $target = $material->byShortId($reply->opens);
        $reason = match (true) {
            $target === null => ConversationRejection::UNKNOWN_ID,
            $target->sceneId !== $scene->sceneId => ConversationRejection::FOREIGN_SCENE,
            isset($said[$target->id()]) => ConversationRejection::ALREADY_SAID,
            default => null,
        };
        if ($reason === null) {
            return $target->id();
        }
        $talk->reject(new ConversationRejection(Ulid::generate(), $index, $attempt, RejectionKind::DroppedOpening, (string) $reason, $reply->callId, [
            'opens' => $reply->opens,
            'target' => $target?->id(),
        ]));

        return null;
    }

    /**
     * The role's answer as it will be said, and which attempt it is: this one when it is the role's own; otherwise the
     * answer to the same move asked once more with the reason — billed for both calls, because the learner waited for
     * both. When that answer says a learner line too (or does not come), the sentences that say one are cut out of it if
     * the rest stands on its own ({@see RoleLines::withoutLearnerLines()}); if nothing would be left, it is said as it is —
     * and counted. Every refused answer is journaled on the line it was for.
     *
     * @param  bool  $mayEnd  may this line close the talk early — not a scene's goodbye, which the server asked for
     * @return array{0: ConversationAgentReply, 1: int}
     */
    private function checked(
        ConversationAgentRequest $request,
        ConversationAgentReply $reply,
        Conversation $talk,
        ConversationMaterialView $material,
        string $turn,
        LanguagePack $target,
        LanguagePack $native,
        int $turnsLeft,
        int $index,
        bool $mayEnd,
    ): array {
        $fault = $this->fault($reply, $talk, $material, $turn, $target, $turnsLeft, $mayEnd);
        if ($fault === null) {
            return [$reply, 1];
        }
        $version = $reply->cost->promptVersion ?? '';
        $this->counters->recordCodes($version, [$fault['code']]);
        $first = static fn (array $more = []): ConversationRejection => new ConversationRejection(
            Ulid::generate(), $index, 1, RejectionKind::RejectedAnswer, $fault['reason'], $reply->callId, [...array_filter(['line' => $fault['line']]), ...$more],
        );

        try {
            $answer = $this->agent->ask($request->redo($fault['reason'], $reply->replyTarget, $fault['line']))->billedWith($reply->cost);
        } catch (ConversationUnavailable) {
            $answer = $reply;
        }
        if ($answer === $reply) {
            // No second answer came: the first is said, and its refusal says what became of it.
            [$said, $outcome] = $this->mended($reply, $fault, $talk, $material, $target, $native, $version);
            $talk->reject($first(['outcome' => $outcome, 'second' => 'unavailable']));

            return [$said, 1];
        }
        $talk->reject($first());
        $second = $this->fault($answer, $talk, $material, $turn, $target, $turnsLeft, $mayEnd);
        if ($second === null) {
            return [$answer, 2];
        }
        [$said, $outcome] = $this->mended($answer, $fault, $talk, $material, $target, $native, $version);
        $talk->reject(new ConversationRejection(Ulid::generate(), $index, 2, RejectionKind::RejectedAnswer, $second['reason'], $answer->callId, ['outcome' => $outcome]));

        return [$said, 2];
    }

    /**
     * An answer asked for twice and still at fault, as it will be said: the learner's line cut out of it, the echo cut
     * out or the pack's neutral line instead of it, or — when nothing of the role's own would be left — as it came.
     *
     * @param  array{reason: string, line: string|null, code: string, kept: string}  $fault  why the first answer was refused
     * @return array{0: ConversationAgentReply, 1: 'cut'|'neutral'|'kept'}
     */
    private function mended(ConversationAgentReply $answer, array $fault, Conversation $talk, ConversationMaterialView $material, LanguagePack $target, LanguagePack $native, string $version): array
    {
        if ($fault['reason'] === RoleLines::REDO_LEARNER_LINE) {
            $cut = RoleLines::withoutLearnerLines($answer->replyTarget, $answer->replyNative, $material->learnerLines(), self::saidSoFar($talk));
            if ($cut !== null) {
                $this->counters->recordCodes($version, [RoleLines::CODE_LEARNER_LINE_CUT]);

                return [$answer->saying($cut['target'], $cut['native']), 'cut'];
            }
        }
        if ($fault['reason'] === RoleLines::REDO_LEARNER_ECHO && RoleLines::echoIn($answer->replyTarget, self::saidSoFar($talk), $target, $this->lines) !== null) {
            $cut = RoleLines::withoutEcho($answer->replyTarget, $answer->replyNative, self::saidSoFar($talk), $target, $this->lines);
            if ($cut !== null) {
                $this->counters->recordCodes($version, [RoleLines::CODE_LEARNER_ECHO_CUT]);

                return [$answer->saying($cut['target'], $cut['native']), 'cut'];
            }
            [$neutral, $translation] = [$target->neutralReply(), $native->neutralReply()];
            if ($neutral !== null && $translation !== null) {
                $this->counters->recordCodes($version, [RoleLines::CODE_LEARNER_ECHO_NEUTRAL]);

                return [$answer->saying($neutral, $translation), 'neutral'];
            }
        }
        $this->counters->recordCodes($version, [$fault['kept']]);

        return [$answer, 'kept'];
    }

    /**
     * What is wrong with an answer, if anything: a line of the learner said as the role's own, a move of the learner said
     * back (any move of the talk — наряд FIX-3 §11), on a rescue the rescued line said again, on any other move a line
     * the role has already said in the talk ({@see RoleLines} guard 4) — and the talk closed with moves still left
     * ({@see ConversationRules::REDO_EARLY_END}), unless the line is a goodbye the server asked for.
     *
     * @return array{reason: 'learner_line'|'learner_echo'|'same_words'|'own_line'|'early_end', line: string|null, code: string, kept: string}|null
     */
    private function fault(ConversationAgentReply $reply, Conversation $talk, ConversationMaterialView $material, string $turn, LanguagePack $target, int $turnsLeft, bool $mayEnd): ?array
    {
        $line = RoleLines::learnerLineIn($reply->replyTarget, $material->learnerLines(), self::saidSoFar($talk));
        if ($line !== null) {
            return ['reason' => RoleLines::REDO_LEARNER_LINE, 'line' => $line, 'code' => RoleLines::CODE_LEARNER_LINE, 'kept' => RoleLines::CODE_LEARNER_LINE_KEPT];
        }
        if (RoleLines::echoIn($reply->replyTarget, self::saidSoFar($talk), $target, $this->lines) !== null) {
            return ['reason' => RoleLines::REDO_LEARNER_ECHO, 'line' => null, 'code' => RoleLines::CODE_LEARNER_ECHO, 'kept' => RoleLines::CODE_LEARNER_ECHO_KEPT];
        }
        if ($turn === 'rescue' && RoleLines::repeats($reply->replyTarget, $talk->lineBeforeLastMove())) {
            return ['reason' => RoleLines::REDO_SAME_WORDS, 'line' => null, 'code' => RoleLines::CODE_SAME_WORDS, 'kept' => RoleLines::CODE_SAME_WORDS_KEPT];
        }
        $own = $turn === 'rescue' ? null : RoleLines::ownLineIn($reply->replyTarget, self::roleSoFar($talk));
        if ($own !== null) {
            return ['reason' => RoleLines::REDO_OWN_LINE, 'line' => $own, 'code' => RoleLines::CODE_OWN_LINE, 'kept' => RoleLines::CODE_OWN_LINE_KEPT];
        }
        if ($mayEnd && $reply->end === ConversationAgentReply::END_NATURAL && $turnsLeft > 0) {
            return ['reason' => ConversationRules::REDO_EARLY_END, 'line' => null, 'code' => ConversationRules::CODE_EARLY_END, 'kept' => ConversationRules::CODE_EARLY_END_KEPT];
        }

        return null;
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
     * Every line the role has said in the talk so far — what a line said again is read against (guard 4).
     *
     * @return list<string>
     */
    private static function roleSoFar(Conversation $talk): array
    {
        $out = [];
        foreach ($talk->turns() as $turn) {
            if ($turn->kind === TurnKind::Agent && $turn->textTarget !== null) {
                $out[] = $turn->textTarget;
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
}
