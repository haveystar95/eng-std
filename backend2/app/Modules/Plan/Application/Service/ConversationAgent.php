<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationAgentReply;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
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
 * It is shown the scene it is in, the scenes still ahead with the lines the learner is preparing,
 * the phrases of the plan, everything said so far and the learner's speech in a field of its own.
 * `TURNS_LEFT` is the only lever on how long the talk runs: at nought the prompt says «say goodbye»,
 * and the role closes the talk itself — nothing here cuts a learner off mid-word.
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
     * What the role is told before one move.
     *
     * @param  'start'|'said'|'rescue'|'skip'  $turn
     */
    public function request(
        Plan $plan,
        Conversation $talk,
        ConversationMaterialView $material,
        string $turn,
        string $heard,
        int $turnsLeft,
    ): ConversationAgentRequest {
        $current = $material->checkpoint($talk->currentCheckpoint());

        return new ConversationAgentRequest(
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level()->value,
            roleTarget: $current->roleTarget ?? '',
            roleNative: $current->roleNative ?? '',
            learnerRoleTarget: $plan->titles()->learnerRoleTarget ?? '',
            learnerRoleNative: $plan->titles()->learnerRoleNative ?? '',
            checkpoints: array_map(self::checkpoint(...), $material->checkpoints),
            currentCheckpoint: $current?->sceneId,
            phrases: array_map(self::phrase(...), $material->phrases),
            history: self::history($talk),
            turn: $turn,
            heard: $heard,
            turnsLeft: max(0, $turnsLeft),
            offTopicStreak: $talk->offTopicStreak(),
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
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function parse(array $payload, TurnCost $cost): ConversationAgentReply
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
        $checkpoint = $payload['checkpoint_done'] ?? null;
        $hint = $payload['next_hint_native'] ?? null;
        /** @var list<string> $phrases */
        $phrases = array_values(array_filter(is_array($payload['phrases_used'] ?? null) ? $payload['phrases_used'] : [], is_string(...)));

        return new ConversationAgentReply(
            replyTarget: trim($target),
            replyNative: trim($native),
            understood: is_bool($understood) ? $understood : null,
            phrasesUsed: $phrases,
            offTopic: ($payload['off_topic'] ?? null) === true,
            checkpointDone: is_string($checkpoint) && trim($checkpoint) !== '' ? $checkpoint : null,
            nextHintNative: is_string($hint) && trim($hint) !== '' ? trim($hint) : null,
            end: $end,
            cost: $cost,
        );
    }

    /**
     * @return array{id: string, title_native: string, about_native: string, role_target: string, role_native: string, key_lines: list<array{target: string, native: string, kind: string, partner: string}>}
     */
    private static function checkpoint(ConversationCheckpoint $checkpoint): array
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
                ],
                $checkpoint->keyLines,
            ),
        ];
    }

    /** @return array{id: string, target: string, native: string} */
    private static function phrase(ConversationPhrase $phrase): array
    {
        return ['id' => $phrase->id(), 'target' => $phrase->textTarget, 'native' => $phrase->textNative];
    }

    /**
     * Everything said so far, oldest first — the role's lines as it said them, the learner's as they
     * were heard. A move with no words (a skip) leaves no line to show.
     *
     * @return list<array{speaker: string, text: string}>
     */
    private static function history(Conversation $talk): array
    {
        $out = [];
        foreach ($talk->turns() as $turn) {
            $text = trim((string) $turn->textTarget);
            if ($text === '') {
                continue;
            }
            $out[] = ['speaker' => $turn->kind === TurnKind::Agent ? 'you' : 'learner', 'text' => $text];
        }

        return $out;
    }
}
