<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * ONE THING THE SERVER REFUSED OF A MOVE OF THE ROLE (наряд FIX-4 §§3, 6; решение владельца 24.09 — одна таблица на оба)
 * — a line of the talk's journal of refusals, written once with the move and never changed:
 *
 * - the role's line it was about (`turnIndex`, the index that line has in the talk) and the call of the model it came
 *   from — which attempt of that line (`attempt`, 1 the first answer, 2 the answer asked for again) and the call's row in
 *   `model_calls` (`modelCallId`, null when the journal of calls could not write it);
 * - what was refused (`kind`) and why (`reason`): a guard's reason for an answer (`learner_line`, `learner_echo`,
 *   `same_words`, `own_line`, `early_end`), and for a door: `foreign_scene`, `already_said`, `unknown_id`;
 * - `detail` — what makes the refusal readable: the line quoted, the id the role named and the target it stands for, what
 *   became of a second answer refused too (`cut`, `neutral`, `kept`).
 *
 * The talk's money counts every attempt on the line itself (its tokens summed): this is where the attempts are told apart.
 */
final readonly class ConversationRejection
{
    public const FOREIGN_SCENE = 'foreign_scene';

    public const ALREADY_SAID = 'already_said';

    public const UNKNOWN_ID = 'unknown_id';

    /**
     * @param  string  $id  the row's own id (a ULID), given when the refusal is made — so writing the talk twice writes it once
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public string $id,
        public int $turnIndex,
        public int $attempt,
        public RejectionKind $kind,
        public string $reason,
        public ?string $modelCallId,
        public array $detail = [],
    ) {}
}
