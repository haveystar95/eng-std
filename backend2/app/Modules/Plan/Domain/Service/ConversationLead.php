<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\TurnKind;

/**
 * WHERE THE ROLE LEADS AND WHAT THE LEARNER IS PROMPTED WITH (наряд FIX-3 §7) — the server's, not the model's.
 *
 * The role opens the door to every target of the talk, ONE BY ONE, in the targets' order ({@see ConversationTargets}):
 * each move it is told the next target whose door no line of its has opened yet and which the learner has not said
 * (`LEAD_TO`). A door is opened once — a target the learner let pass comes back only after every other door has been
 * opened, and a target said is never led to again. The role names the target its line leads to itself (`opens`), which
 * is how the doors are counted; the gym talk of 22.09 asked about «experience» three moves running because nothing said
 * that door had been opened.
 *
 * The scenes a talk has left behind come last: a rehearsal whose role closed a scene before every door of it was opened
 * (the airport check-in of the FIX-3 live run closed its scene with three doors to go) is led on through the scene it is
 * in now and those after it, and back to the scene left behind only when nothing ahead is left — never the doctor asked
 * to open the reception's doors while the reception is behind.
 *
 * THE HINT of the learner's next move is the target the role's last line opened the door to — the answer to the
 * question it just asked, or the question it just gave a reason for. A line that opened none keeps the door of the line
 * before it while that is not said: «Yes? Go on.» after «I'm flying to» still waits for «I'm flying to ___» (the check-in
 * of the FIX-3 live run was prompted with the next target there). Otherwise — a greeting, an answer to the learner —
 * the target the role leads to next. So the hint changes with the move: «У меня около года опыта» hung under three
 * questions of the trainer. Nothing left unsaid — no hint.
 */
final class ConversationLead
{
    /**
     * The target to open the door to now.
     *
     * @param  list<ConversationPhrase>  $targets  the talk's targets, in order
     * @param  list<string>  $saidNow  the targets the move being answered says, not in the journal's credit yet
     */
    public static function next(Conversation $talk, array $targets, array $saidNow = []): ?ConversationPhrase
    {
        $said = [...ConversationOutcomes::heard($talk), ...array_fill_keys($saidNow, true)];
        $opened = array_fill_keys($talk->openedDoors(), true);
        $behind = array_fill_keys($talk->checkpointsDone(), true);
        $ahead = array_values(array_filter($targets, static fn (ConversationPhrase $t): bool => ! isset($behind[$t->sceneId])));
        $left = array_values(array_filter($targets, static fn (ConversationPhrase $t): bool => isset($behind[$t->sceneId])));
        foreach ([...$ahead, ...$left] as $target) {
            if (! isset($said[$target->id()]) && ! isset($opened[$target->id()])) {
                return $target;
            }
        }
        foreach ([...$ahead, ...$left] as $target) {
            if (! isset($said[$target->id()])) {
                return $target;
            }
        }

        return null;
    }

    /**
     * The target the learner is prompted with after the role's line that opened `$opens` (null: none).
     *
     * @param  list<ConversationPhrase>  $targets
     */
    public static function hint(Conversation $talk, array $targets, ?string $opens): ?ConversationPhrase
    {
        $said = ConversationOutcomes::heard($talk);
        $door = $opens ?? self::lastDoor($talk);
        foreach ($targets as $target) {
            if ($target->id() === $door && ! isset($said[$target->id()])) {
                return $target;
            }
        }

        return self::next($talk, $targets);
    }

    /** The door the role's last line in the journal opened — null when it opened none, or when the role has said nothing. */
    private static function lastDoor(Conversation $talk): ?string
    {
        $door = null;
        foreach ($talk->turns() as $turn) {
            if ($turn->kind === TurnKind::Agent) {
                $door = $turn->opensTarget;
            }
        }

        return $door;
    }
}
