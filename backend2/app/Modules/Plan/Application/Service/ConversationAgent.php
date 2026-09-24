<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationAgentReply;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\ConversationUnavailable;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\TurnCost;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\Service\LanguageName;
use Throwable;

/**
 * THE ROLE'S MOVE (наряд CONV-1, п. 4): what the agent is told, what it answers, and what happens
 * when it does not answer. Whether the answer is the role's own is asked by the caller
 * ({@see ConversationMoves}, наряд CONV-2) — here a call is a call.
 *
 * It is told ONE scene — the one it plays now (наряд FIX-4 §4): who it is there, the visit as prepared there, the targets
 * of that scene under the talk's short ids, each said or not yet, and the one to open the door to now (`LEAD_TO`, the
 * server's choice); the lines said in that scene; of the scenes before, what the learner told there, as facts; and the
 * learner's speech in a field of its own. `TURNS_LEFT` is the lever on how long the talk runs — at nought the prompt says
 * «say goodbye» — and `SCENE_END` on how long the scene does: the server closes the scene, the role says goodbye in it.
 *
 * A model that is silent, slow or off the shape is NOT a turn: it throws, nothing is written, and
 * the learner repeats the move (кадр 37-10 «Врач не отвечает — попробуй ещё раз»). That is the
 * opposite of the slot judge, which accepts on silence — there a learner had already said the frame
 * and the vendor must not fail them; here there is simply no reply to show.
 */
final readonly class ConversationAgent
{
    public function __construct(private PlanModelPort $model) {}

    /**
     * What the role is told before one line of its scene.
     *
     * @param  'start'|'said'|'rescue'|'skip'  $turn  `start` — the role speaks first in its scene: the talk's opening, or a new role after the one before said goodbye
     * @param  array<string, true>  $said  every construction said so far, the move being answered included
     */
    public function request(
        Plan $plan,
        Conversation $talk,
        ConversationMaterialView $material,
        ConversationCheckpoint $scene,
        string $turn,
        string $heard,
        int $turnsLeft,
        array $said,
        ?ConversationPhrase $leadTo,
        bool $sceneEnd = false,
    ): ConversationAgentRequest {
        $targets = $material->targetsOf($scene->sceneId);

        return new ConversationAgentRequest(
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level()->value,
            roleTarget: $scene->roleTarget,
            roleNative: $scene->roleNative,
            learnerRoleTarget: $plan->titles()->learnerRoleTarget ?? '',
            learnerRoleNative: $plan->titles()->learnerRoleNative ?? '',
            checkpoints: [self::checkpoint($scene, $said)],
            currentCheckpoint: $scene->sceneId,
            targets: array_map(static fn (ConversationPhrase $t): array => [
                'id' => (string) $material->shortId($t),
                'kind' => $t->kind->value,
                'frame_target' => $t->frameTarget,
                'frame_native' => $t->frameNative,
                'example_target' => $t->exampleTarget,
                'said' => isset($said[$t->id()]),
            ], $targets),
            history: self::history($talk, $scene->sceneId),
            turn: $turn,
            heard: $heard,
            turnsLeft: max(0, $turnsLeft),
            offTopicStreak: $talk->offTopicStreak(),
            leadTo: $leadTo === null ? null : $material->shortId($leadTo),
            earlier: self::earlier($talk, $scene->sceneId),
            sceneEnd: $sceneEnd,
        );
    }

    /** One call of the role: its answer checked into shape, or {@see ConversationUnavailable}. */
    public function ask(ConversationAgentRequest $request): ConversationAgentReply
    {
        try {
            $reply = $this->model->conversationTurn($request);
        } catch (Throwable $e) {
            throw ConversationUnavailable::model(mb_substr($e->getMessage(), 0, 200));
        }

        return self::parse($reply->payload, new TurnCost(
            modelCostUsd: $reply->costUsd,
            modelLatencyMs: $reply->latencyMs,
            model: $reply->model,
            promptVersion: $reply->promptVersion,
            tokensIn: $reply->tokensIn,
            tokensOut: $reply->tokensOut,
        ), $reply->callId);
    }

    /** @param array<string, mixed> $payload */
    private static function parse(array $payload, TurnCost $cost, ?string $callId): ConversationAgentReply
    {
        $target = $payload['reply_target'] ?? null;
        $native = $payload['reply_native'] ?? null;
        $end = $payload['end'] ?? null;
        if (! is_string($target) || trim($target) === '' || ! is_string($native)) {
            throw ConversationUnavailable::model('the answer carries no line');
        }
        if (! is_string($end) || ! in_array($end, [ConversationAgentReply::END_NO, ConversationAgentReply::END_NATURAL, ConversationAgentReply::END_DECLINED], true)) {
            throw ConversationUnavailable::model('the answer does not say whether the talk goes on');
        }

        $understood = $payload['understood'] ?? null;
        $opens = $payload['opens'] ?? null;

        return new ConversationAgentReply(
            replyTarget: trim($target),
            replyNative: trim($native),
            understood: is_bool($understood) ? $understood : null,
            offTopic: ($payload['off_topic'] ?? null) === true,
            opens: is_string($opens) && trim($opens) !== '' ? trim($opens) : null,
            end: $end,
            cost: $cost,
            callId: $callId,
        );
    }

    /**
     * The scene as the role is told it: the visit as prepared, exchange by exchange — each one DONE once the learner has
     * said its target in this talk (наряд FIX-3 §7, the live run: told «his lower back», the receptionist asked the
     * prepared «upper back or lower back?» all the same, three runs of three).
     *
     * @param  array<string, true>  $said  the constructions said so far
     * @return array{id: string, title_native: string, about_native: string, role_target: string, role_native: string, key_lines: list<array{target: string, native: string, kind: string, partner: string, done: bool}>}
     */
    private static function checkpoint(ConversationCheckpoint $checkpoint, array $said): array
    {
        return [
            'id' => $checkpoint->sceneId,
            'title_native' => $checkpoint->titleNative,
            'about_native' => $checkpoint->aboutNative,
            'role_target' => $checkpoint->roleTarget,
            'role_native' => $checkpoint->roleNative,
            'key_lines' => array_map(
                static fn (array $line): array => [
                    'target' => $line['target'],
                    'native' => $line['native'],
                    'kind' => $line['kind'] ?? 'answer',
                    'partner' => $line['partner'] ?? '',
                    'done' => isset($line['phrase_ref']) && isset($said[$checkpoint->sceneId.':'.$line['phrase_ref']]),
                ],
                $checkpoint->keyLines,
            ),
        ];
    }

    /**
     * Every line said so far in the scene, oldest first — the role's lines as it said them, the learner's as they were
     * heard. A move with no words (a skip) leaves no line to show; a line written before the lines knew their scene belongs
     * to the scene the talk is in.
     *
     * @return list<array{speaker: string, text: string}>
     */
    private static function history(Conversation $talk, string $sceneId): array
    {
        $out = [];
        foreach ($talk->turns() as $turn) {
            $text = trim((string) $turn->textTarget);
            if ($text === '' || ($turn->sceneId !== null && $turn->sceneId !== $sceneId)) {
                continue;
            }
            $out[] = ['speaker' => $turn->kind === TurnKind::Agent ? 'you' : 'learner', 'text' => $text];
        }

        return $out;
    }

    /**
     * WHAT THE LEARNER TOLD IN THE SCENES BEFORE (наряд FIX-4 §4) — the facts of the story, not a dialogue to go on: the
     * learner's own lines as heard, oldest first; the lines of the roles before are not the new role's.
     *
     * @return list<string>
     */
    private static function earlier(Conversation $talk, string $sceneId): array
    {
        $out = [];
        foreach ($talk->turns() as $turn) {
            if ($turn->isSpokenByLearner() && $turn->sceneId !== null && $turn->sceneId !== $sceneId) {
                $out[] = trim((string) $turn->textTarget);
            }
        }

        return $out;
    }
}
