<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

/**
 * THE GATE BETWEEN A WRITTEN LESSON AND A DEALT DAY (решение архитектора после GEN-2a, `docs/plan-v2.md` §4).
 *
 * Warnings pass. A fatal finding holds the lesson: P2R is asked for the card it stands at — the next card in
 * {@see LessonGate}'s order, each card once — and the repaired answer is validated again, until no fatal finding
 * is left or {@see LessonGate::MAX_CARDS} cards were asked. A fatal finding still there, or one at no card a
 * repair can take, fails the lesson with its code. A repair the model got wrong (off the card's shape) keeps
 * the answer as it was and uses up its card.
 */
final readonly class LessonGateKeeper
{
    public function __construct(
        private LessonCardRepairer $repairer,
        private LessonValidator $validator,
    ) {}

    /** @param list<LessonViolation> $found the validator's findings over `$answer` */
    public function pass(Lesson $answer, array $found, LessonValidationContext $context, LessonRequest $request): LessonGateOutcome
    {
        $cost = '0.000000';
        $latency = 0;
        $asked = [];
        $gated = LessonGate::fatal($found);

        while (($fatal = LessonGate::fatal($found)) !== []) {
            $cards = LessonGate::cards($fatal);
            $next = null;
            foreach ($cards ?? [] as $card) {
                if (! in_array($card->address, $asked, true)) {
                    $next = $card;
                    break;
                }
            }
            if ($cards === null || $next === null || count($asked) >= LessonGate::MAX_CARDS) {
                return new LessonGateOutcome(null, $found, LessonGate::failReason($fatal), $asked, $gated, $fatal, $cost, $latency);
            }

            $asked[] = $next->address;
            $repair = $this->repairer->repairIn($answer, $next, $found, $context, $request);
            $cost = ModelCall::addCosts($cost, $repair->costUsd);
            $latency += $repair->latencyMs;
            if ($repair->status === LessonCardRepairOutcome::REPAIRED && $repair->answer !== null) {
                $answer = $repair->answer;
                $found = $this->validator->run($answer, $context);
            }
        }

        return new LessonGateOutcome($answer, $found, null, $asked, $gated, [], $cost, $latency);
    }
}
