<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

/**
 * Which way the pair broke (QA-22).
 *
 * Both directions make a key unanswerable, and they are not the same defect. LOST is the original
 * one: the source addresses someone and the translation does not, so several different sources fit
 * the same key. EXTRA is its mirror: the translation says something the source never did — «Я
 * **хорошо** лажу со своей командой» for `I get along with my team` — so the learner who answers
 * the key correctly is told they are wrong, because the key asked for a `well` that isn't there.
 *
 * A report that merged them would be unreadable: the fix for LOST is to put a word back into the
 * translation, and the fix for EXTRA is to take one out (or to put it into the source).
 */
enum AddresseeDirection: string
{
    /** The source addresses someone; the translation dropped it. */
    case Lost = 'lost';

    /** The translation carries a word of the group; the source licenses nothing of the sort. */
    case Extra = 'extra';
}
