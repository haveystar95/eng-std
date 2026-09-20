<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * HOW A SPOKEN ATTEMPT IS COMPARED WITH WHAT WAS ASKED FOR (наряд FIX-2, п. 2) — the whole of «did they say it»,
 * in two values, because a card asking for a line ON SCREEN and a card asking for the learner's own sentence are
 * two different questions and one threshold cannot answer both.
 *
 * Which one a card asks in travels ON THE WIRE, in the card's own payload, so the phone and the server judge the
 * same attempt by the same rule without either of them deriving it from the kind a second time.
 *
 * - `repeat` — THE TEXT IS ON SCREEN and the learner reads or repeats it: every content word of the expected text,
 *   in its order. Nothing is forgiven but the words a recogniser eats (the target pack's `unstressed_words`) and
 *   what is spelling rather than speech; extra words heard around them do not matter. A share would be the wrong
 *   bar here: «He has a rush» covers 70 % of «He has a rash» and is a different sentence.
 * - `free` — THE LEARNER SAYS THEIR OWN SENTENCE and only the KEY of it is checked: the frame's own words outside
 *   the window, by coverage, because what goes into the window is the learner's and the words around it are what
 *   the card actually asked for.
 */
enum SpeechMode: string
{
    case Repeat = 'repeat';
    case Free = 'free';
}
