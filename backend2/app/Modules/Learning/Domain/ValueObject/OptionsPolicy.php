<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * Where a choice card's WRONG options come from. Stored per mode, because it is a product
 * judgement about what the card is asking, not a property of the mode's code.
 *
 *   distant   the other terms in THIS session. Unmistakably different, so the card is answerable
 *             by knowing the word and by nothing else. This is what a first meeting needs: the
 *             learner has no basis yet on which to tell near-misses apart, and a card they cannot
 *             pass teaches them only that they are failing.
 *   standard  the enrichment distractors — deliberately nearly right. That IS the exercise once
 *             the word is known: the difference between «explain the fees» and «explain fees» is
 *             the thing being tested, and it is worth testing only after the word itself is not.
 *
 * A graduated pair always gets `standard` whatever is stored: `distant` exists to make a card
 * winnable at a first meeting, and a pair off the ladder is past that by definition.
 */
enum OptionsPolicy: string
{
    case Distant = 'distant';
    case Standard = 'standard';
}
