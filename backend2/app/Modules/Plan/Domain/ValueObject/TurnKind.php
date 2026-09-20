<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * ONE LINE OF THE TALK, by what produced it (наряд CONV-1). The journal keeps both speakers' lines
 * in one append-only list, so the kind says who spoke as well as why:
 *
 * - `agent` — the role's line: the opening one the server writes when the talk starts, every answer
 *   to what was heard, and the farewell;
 * - `said` — the learner spoke;
 * - `rescue` — «Не понял» / «Sorry?»: the role repeats itself simpler and slower. No judgements are
 *   written for it and it is not «said it themselves» — it is a request, not a reply;
 * - `skip` — the learner let the turn go. Nothing is judged, and the role carries the scene on.
 */
enum TurnKind: string
{
    case Agent = 'agent';
    case Said = 'said';
    case Rescue = 'rescue';
    case Skip = 'skip';

    public function speaker(): Speaker
    {
        return $this === self::Agent ? Speaker::Partner : Speaker::Learner;
    }

    /** What the learner may send as a move of their own. */
    public function isLearners(): bool
    {
        return $this !== self::Agent;
    }

    /**
     * What the prompt calls this move. The role's own line is `start`: it is the only line nobody
     * asked for — the opening one, written before the learner has said anything.
     *
     * @return 'start'|'said'|'rescue'|'skip'
     */
    public function promptTurn(): string
    {
        return match ($this) {
            self::Agent => 'start',
            self::Said => 'said',
            self::Rescue => 'rescue',
            self::Skip => 'skip',
        };
    }

    /** Is this move judged at all — a rescue and a skip are not the learner's reply. */
    public function isJudged(): bool
    {
        return $this === self::Said;
    }
}
