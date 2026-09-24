<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationOutcome;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\FrameState;
use App\Modules\Plan\Domain\ValueObject\SceneEvent;
use App\Modules\Plan\Domain\ValueObject\TurnKind;

/**
 * THE SUMMARY, READ OFF THE JOURNAL (кадр 37-12, наряд CONV-1).
 *
 * «Сказал сам» counts the moves where the learner actually said something — a rescue is asking to
 * hear it again and a skip is letting it go, and neither is a line of one's own. «Понял вопросы»
 * counts the moves the role judged `understood: false`; a move with no judgement (a rescue, a skip,
 * a turn the model could not rule on) is not a misunderstanding, because nothing was claimed about
 * it. «Фразы дня» are the talk's TARGETS ({@see ConversationTargets}, наряд CONV-2, п. 10) that the judge
 * heard ({@see FrameJudge}, наряд FIX-4 §2) anywhere in the talk — the same list the entry card showed and the
 * ribbon's strip ticked off, so the count on the summary is the count of what the learner was asked for. A construction
 * of a scene said that is no target is «ещё вспомнил» (`extraSaid`): in the summary, never a target.
 */
final class ConversationOutcomes
{
    /** @param list<ConversationPhrase> $targets the phrases the talk asks for ({@see ConversationTargets::of()}) */
    public static function of(Conversation $talk, array $targets): ConversationOutcome
    {
        $said = 0;
        $rescues = 0;
        $notUnderstood = 0;
        foreach ($talk->turns() as $turn) {
            if ($turn->kind === TurnKind::Rescue) {
                $rescues++;
            }
            if ($turn->isSpokenByLearner()) {
                $said++;
            }
            if ($turn->understood === false) {
                $notUnderstood++;
            }
        }

        $heard = self::heard($talk);
        $usedIds = [];
        $notSaid = [];
        $targetIds = [];
        foreach ($targets as $target) {
            $targetIds[$target->id()] = true;
            if (isset($heard[$target->id()])) {
                $usedIds[] = $target->id();
            } else {
                $notSaid[] = $target->id();
            }
        }

        return new ConversationOutcome(
            saidCount: $said,
            phrasesUsed: $usedIds,
            phrasesTotal: count($targets),
            notSaid: $notSaid,
            understoodAll: $notUnderstood === 0,
            notUnderstood: $notUnderstood,
            rescues: $rescues,
            endedReason: $talk->endedReason(),
            minutes: $talk->minutes(),
            extraSaid: array_values(array_filter(array_keys($heard), static fn (string $id): bool => ! isset($targetIds[$id]))),
        );
    }

    /**
     * Every construction the judge heard said in the talk so far, by id, in the order it was first said — what a target's
     * «said» reads.
     *
     * @return array<string, true>
     */
    public static function heard(Conversation $talk): array
    {
        $out = [];
        foreach ($talk->turns() as $turn) {
            foreach ($turn->phrasesUsed as $id) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /** Where a construction stands in the talk: said by any move, else said almost by any, else none. */
    public static function stateOf(Conversation $talk, string $id): FrameState
    {
        $state = FrameState::None;
        foreach ($talk->turns() as $turn) {
            if (in_array($id, $turn->phrasesUsed, true)) {
                return FrameState::Said;
            }
            if (in_array($id, $turn->phrasesAlmost, true)) {
                $state = FrameState::Almost;
            }
        }

        return $state;
    }

    /**
     * DID A LIMIT END THE TALK (наряд FIX-4 §4) — the rule, on what the journal keeps: an ended talk whose reason is
     * `limit` (money, minutes), or `natural` on a last line of the role that is a scene's goodbye the server asked for
     * (`scene_event: end`) while a scene of the talk is not walked — the talk's moves ran out first. A day's talk has no
     * goodbyes of scenes: its moves are its length, and spending them is its end.
     */
    public static function endedByLimit(bool $ended, ?string $reason, ?string $lastRoleEvent, bool $scenesWalked): bool
    {
        if (! $ended) {
            return false;
        }

        return $reason === ConversationEnd::Limit->value
            || ($reason === ConversationEnd::Natural->value && $lastRoleEvent === SceneEvent::End->value && ! $scenesWalked);
    }

    /** @param list<ConversationTurn> $turns */
    public static function lastAgentText(array $turns): ?string
    {
        for ($i = count($turns) - 1; $i >= 0; $i--) {
            if ($turns[$i]->kind === TurnKind::Agent) {
                return $turns[$i]->textTarget;
            }
        }

        return null;
    }
}
