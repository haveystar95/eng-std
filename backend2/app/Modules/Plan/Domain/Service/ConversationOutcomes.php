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
 * it. «Фразы дня» are the talk's TARGETS ({@see ConversationTargets}, наряд CONV-2, п. 10) that the code
 * matched ({@see SpokenPhrases}) anywhere in the talk — the same list the entry card showed and the
 * ribbon's strip ticked off, so the count on the summary is the count of what the learner was asked for.
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
        foreach ($targets as $target) {
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
        );
    }

    /**
     * Every phrase of the plan the code heard in the talk so far, by id — what a target's «said» reads.
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

    /**
     * How long the day's talks took in minutes, for the DAY's own count (наряд CONV-2, п. 3): the talked time of every
     * talk of the day, a replay and one still running included — «19 минут» of a closed day is how long the day took —
     * added up in seconds and rounded up once.
     *
     * @param  list<Conversation>  $talks
     */
    public static function minutesOf(array $talks): int
    {
        $seconds = 0;
        foreach ($talks as $talk) {
            $seconds += $talk->activeSeconds();
        }

        return (int) ceil($seconds / 60);
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
