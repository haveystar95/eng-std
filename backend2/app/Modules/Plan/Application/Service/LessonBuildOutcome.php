<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

/** What the lesson call came back with: the model's answer and the validator's findings over it, or why it failed. */
final readonly class LessonBuildOutcome
{
    /** @param list<array{code: string, address: string, detail: string}> $findings */
    private function __construct(
        public ?Lesson $lesson,
        public ?string $failReason,
        public ?ModelCall $call,
        public array $findings,
    ) {}

    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public static function ok(Lesson $answer, ModelCall $call, array $findings): self
    {
        return new self($answer, null, $call, $findings);
    }

    public static function failed(string $reason, ?ModelCall $call): self
    {
        return new self(null, $reason, $call, []);
    }
}
