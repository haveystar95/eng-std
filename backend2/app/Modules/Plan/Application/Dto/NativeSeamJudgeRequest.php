<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE SEAM JUDGE'S ONE CALL OF A DAY (`lesson_seam_judge.v1.3`, наряды GEN-2b, GEN-4c): the learner's language by name, and
 * every native sentence the day's frames make with their fillers — the filler's address, the native frame, the native filler,
 * and the sentence the server put together from them; and in the same call the target's language by name and every reply of
 * the partner to a question of the learner's ({@see \App\Modules\Plan\Domain\Lesson\AskReplies}) — the partner line's id, the
 * question with its slot, the values it is asked with, the reply.
 */
final readonly class NativeSeamJudgeRequest
{
    /**
     * @param  list<array{id: string, pattern: string, value: string, sentence: string}>  $items
     * @param  list<array{id: string, question: string, values: list<string>, reply: string}>  $replies
     */
    public function __construct(
        public string $nativeLanguage,
        public array $items,
        public string $targetLanguage = '',
        public array $replies = [],
    ) {}

    /** @return list<string> the ids of the native sentences */
    public function ids(): array
    {
        return array_map(static fn (array $item): string => $item['id'], $this->items);
    }

    /** @return list<string> the ids of the replies — their partner lines' */
    public function replyIds(): array
    {
        return array_map(static fn (array $reply): string => $reply['id'], $this->replies);
    }
}
