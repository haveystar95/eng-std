<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\Message;

/**
 * `exchange.shape` — FATAL (a shape guard beside the order's list, наряд GEN-4: a day deals every exchange as two lines, the
 * partner's and the learner's). Every exchange has exactly two messages, one of A and one of B, the first said by the one
 * who opens it.
 */
final class ExchangeShape implements DialogueRule
{
    public const CODE = 'exchange.shape';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $out = [];
        foreach ($dialogue->exchanges as $e) {
            $messages = $e->exchange->messages;
            $speakers = array_map(static fn (Message $m): string => $m->speaker, $messages);
            $sorted = $speakers;
            sort($sorted);
            if (count($messages) !== 2 || $sorted !== [Message::SPEAKER_PARTNER, Message::SPEAKER_LEARNER] || $speakers[0] !== $e->exchange->initiator) {
                $out[] = new LessonViolation(self::CODE, 'x'.$e->step(), 'the exchange is '.implode(' → ', $speakers).", opened by {$e->exchange->initiator}");
            }
        }

        return $out;
    }
}
