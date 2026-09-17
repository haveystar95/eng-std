<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * THE STORY SO FAR (`lesson_day.v4.6`, THE STORY SO FAR; наряд GEN-3): every content day of the plan before this one whose
 * lesson is written, oldest first — none on the first day. What the next lesson is given to read and what its words and
 * frames may not repeat.
 */
final readonly class EarlierDays
{
    /** @param list<EarlierDay> $days oldest first */
    public function __construct(public array $days = []) {}

    public function isEmpty(): bool
    {
        return $this->days === [];
    }

    /**
     * Every word an earlier day taught, with the day that taught it first.
     *
     * @return list<array{term: string, day: int}>
     */
    public function words(): array
    {
        $out = [];
        foreach ($this->days as $day) {
            foreach ($day->words as $word) {
                $out[] = ['term' => $word, 'day' => $day->number];
            }
        }

        return $out;
    }

    /**
     * Every frame an earlier day taught, in both languages, with its day.
     *
     * @return list<array{target: string, native: string, day: int}>
     */
    public function frames(): array
    {
        $out = [];
        foreach ($this->days as $day) {
            foreach ($day->frames as $frame) {
                $out[] = [...$frame, 'day' => $day->number];
            }
        }

        return $out;
    }
}
