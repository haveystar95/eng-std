<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHERE A LINE OF THE ROLE STANDS IN ITS SCENE (наряд FIX-4 §4) — in a talk over several scenes (the rehearsal, a review
 * day): `start` — the new role greets the learner first, the scene begins; `end` — the role of the scene says goodbye,
 * the scene is over. The lines in between carry none, and so does every line of a day's talk: its scene is one.
 */
enum SceneEvent: string
{
    case Start = 'start';
    case End = 'end';
}
