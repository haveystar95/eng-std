<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One question about the whole visit, heard without text (`lesson_day.v4.4`, LISTENING): in the
 * learner's language, three options with the right one marked (already at its shuffled place).
 * Additive (GEN-2a): the client does not read it yet.
 */
final readonly class WindowListeningView
{
    /** @param list<array{text: string, correct: bool}> $options */
    public function __construct(
        public string $question,
        public array $options,
        public string $explanation,
    ) {}
}
