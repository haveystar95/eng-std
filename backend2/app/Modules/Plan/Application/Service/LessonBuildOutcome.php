<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

/**
 * What the lesson call came back with: the answer that passed the gate (repaired or as written) and the
 * validator's findings over it, or why it failed — with the findings that failed it, when the gate did
 * (`failedOnGate`: a fatal finding left after the repairs — the one failure the server builds again on its own, наряд
 * LANG-1b §1). `rebuilt` — this is the second build of the lesson, and its call carries the first one's money and time.
 */
final readonly class LessonBuildOutcome
{
    /** @param list<array{code: string, address: string, detail: string}> $findings */
    private function __construct(
        public ?Lesson $lesson,
        public ?string $failReason,
        public ?ModelCall $call,
        public array $findings,
        public bool $failedOnGate = false,
        public bool $rebuilt = false,
    ) {}

    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public static function ok(Lesson $answer, ModelCall $call, array $findings): self
    {
        return new self($answer, null, $call, $findings);
    }

    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public static function failed(string $reason, ?ModelCall $call, array $findings = []): self
    {
        return new self(null, $reason, $call, $findings);
    }

    /**
     * The gate held the lesson: a fatal finding was left after the repairs, or stood at no card a repair can take.
     *
     * @param  list<array{code: string, address: string, detail: string}>  $findings
     */
    public static function failedOnGate(string $reason, ModelCall $call, array $findings): self
    {
        return new self(null, $reason, $call, $findings, failedOnGate: true);
    }

    /** This build with the one before it counted in: its money, its time and its lesson calls ({@see ModelCall::after()}). */
    public function after(ModelCall $earlier): self
    {
        return new self($this->lesson, $this->failReason, $this->call?->after($earlier) ?? $earlier, $this->findings, $this->failedOnGate, rebuilt: true);
    }
}
