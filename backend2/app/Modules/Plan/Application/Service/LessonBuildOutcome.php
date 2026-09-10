<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

final readonly class LessonBuildOutcome
{
    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    private function __construct(
        public ?Lesson $lesson,
        public ?string $failReason,
        public ?ModelCall $call,
        public array $findings,
    ) {}

    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    public static function ok(Lesson $lesson, ModelCall $call, array $findings): self
    {
        return new self($lesson, null, $call, $findings);
    }

    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    public static function failed(string $reason, ?ModelCall $call, array $findings): self
    {
        return new self(null, $reason, $call, $findings);
    }
}
