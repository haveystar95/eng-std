<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * A talk as the checks see it: its role's lines and how many of them opened a construction (`opens_target`), how it ended,
 * and every voiced line of the role with the voice it was said in against the voice its cast gives it. `openersRecorded`
 * — the talk began once `opens_target` was being written (23.09, FIX-3 §7); before that a role line carries none, and its
 * absence says nothing.
 */
final readonly class TalkFact
{
    /** @param list<array{turn: int, voice: string, expected: string|null}> $voicedLines */
    public function __construct(
        public string $id,
        public int $day,
        public string $type,
        public bool $ended,
        public ?string $endedReason,
        public int $roleLines,
        public int $openers,
        public array $voicedLines,
        public bool $openersRecorded = true,
    ) {}
}
