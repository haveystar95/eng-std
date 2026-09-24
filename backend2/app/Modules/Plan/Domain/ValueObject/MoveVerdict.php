<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHAT ONE MOVE OF THE LEARNER SAID of the constructions it was judged against (наряд FIX-4 §2,
 * {@see \App\Modules\Plan\Domain\Service\FrameJudge}): the ids (`<scene>:<ref>`) of those it said and of those it said
 * almost, and what went into the window of each one said — as it was said.
 */
final readonly class MoveVerdict
{
    /**
     * @param  list<string>  $said
     * @param  list<string>  $almost
     * @param  array<string, string|null>  $values  by id, for the frames said; null for a frame without a window
     */
    public function __construct(
        public array $said = [],
        public array $almost = [],
        public array $values = [],
    ) {}

    public function stateOf(string $id): FrameState
    {
        return match (true) {
            in_array($id, $this->said, true) => FrameState::Said,
            in_array($id, $this->almost, true) => FrameState::Almost,
            default => FrameState::None,
        };
    }
}
