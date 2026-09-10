<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

/** What the plan call came to: a blueprint, an «unclear» verdict, or a failure — with the bill. */
final readonly class PlanBuildOutcome
{
    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    private function __construct(
        public ?Blueprint $blueprint,
        public ?string $unclearReason,
        public ?string $failReason,
        public ?ModelCall $call,
        public array $findings,
    ) {}

    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    public static function ok(Blueprint $blueprint, ModelCall $call, array $findings): self
    {
        return new self($blueprint, null, null, $call, $findings);
    }

    public static function unclear(string $reason, ModelCall $call): self
    {
        return new self(null, $reason, null, $call, []);
    }

    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    public static function failed(string $reason, ?ModelCall $call, array $findings): self
    {
        return new self(null, null, $reason, $call, $findings);
    }
}
