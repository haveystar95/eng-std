<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\TurnKind;

/**
 * WHERE THE ROLE LEADS AND WHAT THE LEARNER IS PROMPTED WITH — the server's, not the model's, and within the scene the
 * talk is in (наряды FIX-3 §7, FIX-4 §§3, 5).
 *
 * THE LEAD (`LEAD_TO`): the role opens the door to the targets of its scene one by one, in their order. A target the move
 * being answered said ALMOST is led to again at once — «Открытие „почти"-цели — допустимо и желательно»: the learner was
 * one word from it. Otherwise the first target not said whose door no line of the role has opened yet, and when every
 * door has been opened, the first not said. The targets of other scenes are no business of this role: the ones behind
 * were its predecessor's, the ones ahead are its successor's (the gym receptionist asked the trainer's question on move 7
 * of the owner's rehearsal).
 *
 * THE HINT of the learner's next move is ONE target of the scene, offered WHOLE — the lesson's own sentence of it, «У меня
 * есть боль в плече.», not «у меня есть …» (наряд FIX-4 §5): the one the move before said almost — then with its exact
 * line as well («I have some shoulder pain.»), for one move —, else the one the role's line has just opened, else the
 * first target of the scene not said. So the hint changes with the move, and stays the same two moves running only for the
 * same target with no «almost» between. Nothing left unsaid in the scene — no hint.
 */
final class ConversationLead
{
    /**
     * The target to open the door to now.
     *
     * @param  list<ConversationPhrase>  $targets  the targets of the scene the talk is in, in order
     * @param  array<string, true>  $said  every construction said so far, the move being answered included
     * @param  list<string>  $almost  the targets the move being answered said almost
     */
    public static function next(Conversation $talk, array $targets, array $said, array $almost = []): ?ConversationPhrase
    {
        foreach ($targets as $target) {
            if (in_array($target->id(), $almost, true) && ! isset($said[$target->id()])) {
                return $target;
            }
        }
        $opened = array_fill_keys($talk->openedDoors(), true);
        foreach ($targets as $target) {
            if (! isset($said[$target->id()]) && ! isset($opened[$target->id()])) {
                return $target;
            }
        }
        foreach ($targets as $target) {
            if (! isset($said[$target->id()])) {
                return $target;
            }
        }

        return null;
    }

    /**
     * The target the learner is prompted with after the role's line, and whether its exact line goes with it (after an
     * almost).
     *
     * @param  list<ConversationPhrase>  $targets  the targets of the scene the line is said in, in order
     * @param  array<string, true>  $said  every construction said so far
     * @param  list<string>  $almost  the targets the move this line answers said almost — none when it answers no move
     * @param  string|null  $opened  the target the line opened the door to, as the server accepted it
     * @return array{target: ConversationPhrase, exact: bool}|null
     */
    public static function hint(array $targets, array $said, array $almost, ?string $opened): ?array
    {
        foreach ($targets as $target) {
            if (in_array($target->id(), $almost, true) && ! isset($said[$target->id()])) {
                return ['target' => $target, 'exact' => true];
            }
        }
        foreach ($targets as $target) {
            if ($target->id() === $opened && ! isset($said[$target->id()])) {
                return ['target' => $target, 'exact' => false];
            }
        }
        foreach ($targets as $target) {
            if (! isset($said[$target->id()])) {
                return ['target' => $target, 'exact' => false];
            }
        }

        return null;
    }

    /**
     * The learner's move a line of the role answers — the line right before it, when that is the learner's. A new role's
     * greeting follows its predecessor's goodbye and answers no move.
     */
    public static function moveBefore(Conversation $talk, ConversationTurn $line): ?ConversationTurn
    {
        $before = null;
        foreach ($talk->turns() as $turn) {
            if ($turn->index === $line->index) {
                return $before !== null && $before->kind !== TurnKind::Agent ? $before : null;
            }
            $before = $turn;
        }

        return null;
    }
}
