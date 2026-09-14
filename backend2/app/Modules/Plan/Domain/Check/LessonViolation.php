<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

/**
 * ONE FINDING OF THE LESSON VALIDATOR: a code, the address of the card it is about, and why.
 *
 * The address is what a repair is asked by: `p3` — frame 3, `p3.f2` — its second filler, `B3` / `A3`
 * — the learner's / the partner's message of exchange 3, `x3` — exchange 3 itself, `x3.check` — its
 * check, `L2` — listening question 2, `v4` — vocabulary item 4; `lesson` for what belongs to no one
 * card (a count, a share). The detail is English and about this card only.
 */
final readonly class LessonViolation
{
    public function __construct(
        public string $code,
        public string $address,
        public string $detail,
    ) {}

    /** @return array{code: string, address: string, detail: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'address' => $this->address, 'detail' => $this->detail];
    }

    public static function exchange(int $step): string
    {
        return 'x'.$step;
    }

    public static function learner(int $step): string
    {
        return 'B'.$step;
    }

    public static function partner(int $step): string
    {
        return 'A'.$step;
    }

    public static function check(int $step): string
    {
        return 'x'.$step.'.check';
    }

    public static function listening(int $index): string
    {
        return 'L'.($index + 1);
    }
}
