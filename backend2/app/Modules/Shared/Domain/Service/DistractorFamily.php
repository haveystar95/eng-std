<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * WHAT MAY STAND BESIDE AN ANSWER — the one statement of the rule, read by both sides of it.
 *
 * Two places need it and they must never disagree. {@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentDistractorReader}
 * picks the options; {@see \App\Modules\Learning\Application\Service\PlanStandings} decides whether
 * a choice card is owed at all, because a checklist that keeps waiting for a card the pool cannot
 * furnish is a plan day that never passes and a next day that is never written. A copy of this
 * function beside either of them is how one of the two quietly stops matching.
 *
 * ## Kind for kind, absolutely
 *
 * `word` against `word`, `chunk` against `chunk`, `line` against `line`, and «no kind at all» —
 * ordinary vocabulary, which is nearly everything outside a plan — against its own. `null` is NOT
 * «word»: a plan's `word` means «this card is a single word IN THIS DAY», and a catalogue term has
 * never been in a day.
 *
 * The rule used to put `word`, `chunk` and `null` in one «substitution» family, on the argument
 * that kind-for-kind starves — a day carries two connectors, the catalogue carries no `kind` at
 * all, so a `chunk` could only ever be offered nothing. The facts were right and the remedy was
 * wrong: the answer to a starved pool is to DROP the choice, not to fill it with another kind. A
 * connector offered two single words is answerable by shape, which is the very defect the family
 * rule exists to prevent.
 *
 * ## Inside `line`, the FORM splits again
 *
 * A question is offered questions and a statement statements. Live run Д-2, photographed twice: the
 * card asked for «Could you repeat?» and offered three statements, so the answer was the one with
 * the question mark and no reading was required.
 *
 * Ends-with-`?`, and nothing cleverer. It is the mark the writing system already uses for exactly
 * this distinction, it is present in the option text the learner sees, and a grammatical test would
 * be a rule per language where this is a rule for all of them.
 */
final class DistractorFamily
{
    public const NONE = 'none';

    public const LINE_QUESTION = 'line:question';

    public const LINE_STATEMENT = 'line:statement';

    private const LINE = 'line';

    public static function of(?string $kind, string $text): string
    {
        if ($kind !== self::LINE) {
            return $kind ?? self::NONE;
        }

        return str_ends_with(rtrim($text), '?') ? self::LINE_QUESTION : self::LINE_STATEMENT;
    }
}
