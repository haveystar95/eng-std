<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * WHAT ONE P-Listen CALL ANSWERS — and it answers two different questions, on purpose.
 *
 * The entry asks this prompt twice, at two moments, and the two answers are not the same shape:
 *
 *   on the GOAL step, before the language is chosen — `continuations` only. «Дописать за тебя»
 *   needs two ways to carry the learner's own sentence further, and there is no language yet to
 *   write a spoken line in;
 *   after the LANGUAGE step — both. The three lines are the listening warm-up, and the
 *   continuations come along because the same call already knows the goal.
 *
 * ONE DTO for both, with the empty half left empty, rather than two: the caller decides what it
 * shows by what it asked for, and a shape that changed with the request would put that decision in
 * two places. Either list may be empty, and an empty one always means the same thing — «этого блока
 * нет», never «блок сломался».
 */
final readonly class ListenWarmupView
{
    /**
     * @param  list<ListenLineView>  $lines  the warm-up; empty when the target language was not
     *         known at the time of the call, or when the answer held nothing usable
     * @param  list<string>  $continuations  «Дописать за тебя» — the learner's goal carried a little
     *         further, in the support language. Empty means the block is not shown.
     */
    public function __construct(
        public array $lines = [],
        public array $continuations = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lines' => array_map(static fn (ListenLineView $l): array => $l->toArray(), $this->lines),
            'continuations' => $this->continuations,
        ];
    }
}
