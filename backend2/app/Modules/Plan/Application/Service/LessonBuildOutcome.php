<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

/**
 * What a day's build came back with (наряд GEN-4): the lesson assembled from its two stages with the skeleton it was built
 * from, and the findings left over them — or why it failed: a stage whose fatal finding was still there after its one repeat
 * (`fatal: …`), an answer twice off its schema. `call` is the build's money, time and stage calls; `log` is what the build did
 * ({@see LessonBuildLog}).
 */
final readonly class LessonBuildOutcome
{
    /** @param list<array{code: string, address: string, detail: string}> $findings */
    private function __construct(
        public ?Lesson $lesson,
        public ?Skeleton $skeleton,
        public ?string $failReason,
        public ?ModelCall $call,
        public array $findings,
        public LessonBuildLog $log,
    ) {}

    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public static function ok(Lesson $lesson, Skeleton $skeleton, ModelCall $call, array $findings, LessonBuildLog $log): self
    {
        return new self($lesson, $skeleton, null, $call, $findings, $log);
    }

    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public static function failed(string $reason, ?ModelCall $call, array $findings, LessonBuildLog $log): self
    {
        return new self(null, null, $reason, $call, $findings, $log);
    }
}
