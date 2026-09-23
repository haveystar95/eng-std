<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * A talk as the checks see it: its role's lines and how many of them opened a construction (`opens_target`), how it ended,
 * and every voiced line of the role with the voice it was said in against the voice its cast gives it.
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
    ) {}
}
