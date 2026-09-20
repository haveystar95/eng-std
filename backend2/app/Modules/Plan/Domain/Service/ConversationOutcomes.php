<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\ValueObject\ConversationOutcome;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\TurnKind;

/**
 * THE SUMMARY, READ OFF THE JOURNAL (кадр 37-12, наряд CONV-1).
 *
 * «Сказал сам» counts the moves where the learner actually said something — a rescue is asking to
 * hear it again and a skip is letting it go, and neither is a line of one's own. «Понял вопросы»
 * counts the moves the role judged `understood: false`; a move with no judgement (a rescue, a skip,
 * a turn the model could not rule on) is not a misunderstanding, because nothing was claimed about
 * it. «Фразы дня» are the ones the code matched ({@see SpokenPhrases}) anywhere in the talk.
 */
final class ConversationOutcomes
{
    /** @param list<ConversationPhrase> $phrases every phrase of the scenes the talk covers */
    public static function of(Conversation $talk, array $phrases): ConversationOutcome
    {
        $said = 0;
        $rescues = 0;
        $notUnderstood = 0;
        $used = [];
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
            foreach ($turn->phrasesUsed as $id) {
                $used[$id] = true;
            }
        }

        $known = [];
        foreach ($phrases as $phrase) {
            $known[$phrase->id()] = true;
        }
        $usedIds = array_values(array_filter(array_keys($used), static fn (string $id): bool => isset($known[$id])));
        $notSaid = array_values(array_filter(array_keys($known), static fn (string $id): bool => ! isset($used[$id])));

        return new ConversationOutcome(
            saidCount: $said,
            phrasesUsed: $usedIds,
            phrasesTotal: count($known),
            notSaid: $notSaid,
            understoodAll: $notUnderstood === 0,
            notUnderstood: $notUnderstood,
            rescues: $rescues,
            endedReason: $talk->endedReason(),
            minutes: $talk->minutes(),
        );
    }

    /**
     * How long the talk took in minutes for the DAY's own count — a talk still running adds the
     * minutes it has already taken, so «19 минут» of a closed day includes the conversation.
     */
    public static function minutesOf(?Conversation $talk): int
    {
        return $talk?->minutes() ?? 0;
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
