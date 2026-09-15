<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE SEAM JUDGE'S ONE CALL OF A DAY (`lesson_seam_judge.v1`, наряд GEN-2b): the learner's language by name, and
 * every native sentence the day's frames make with their fillers — the filler's address, the native frame, the
 * native filler, and the sentence the server put together from them.
 */
final readonly class NativeSeamJudgeRequest
{
    /** @param list<array{id: string, pattern: string, value: string, sentence: string}> $items */
    public function __construct(
        public string $nativeLanguage,
        public array $items,
    ) {}

    /** @return list<string> */
    public function ids(): array
    {
        return array_map(static fn (array $item): string => $item['id'], $this->items);
    }
}
