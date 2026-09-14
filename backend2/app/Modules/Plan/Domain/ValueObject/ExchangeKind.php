<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Lesson\Message;

/**
 * WHAT AN EXCHANGE OF THE VISIT IS (`lesson_day.v4.4`, EXCHANGE KINDS).
 *
 * `answer` — the partner speaks first and the learner replies; `ask` — the learner asks and the
 * partner answers; `rescue` — the learner did not catch the previous partner line and asks for it
 * again, and the partner repeats the same content. A frame is taken from the learner's line of an
 * answer or an ask; a rescue line has none. The same words name a frame's kind (`answer` | `ask`).
 */
enum ExchangeKind: string
{
    case Answer = 'answer';
    case Ask = 'ask';
    case Rescue = 'rescue';

    /** Who speaks first: the partner in an answer, the learner in an ask and in a rescue. */
    public function initiator(): string
    {
        return $this === self::Answer ? Message::SPEAKER_PARTNER : Message::SPEAKER_LEARNER;
    }

    /** Does the learner's line of this exchange stand on a frame? */
    public function takesFrame(): bool
    {
        return $this !== self::Rescue;
    }
}
