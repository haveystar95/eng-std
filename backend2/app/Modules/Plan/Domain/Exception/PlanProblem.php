<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use RuntimeException;

/**
 * Base of every plan error the client switches on: one RFC 7807 shape, a stable `code`, and the
 * meta the screen needs to say why (which day holds the lock, when it opens, what the lesson's
 * state is). Subclasses name the code and the status; the message is for the log.
 */
abstract class PlanProblem extends RuntimeException implements ProblemDetails
{
    /** @param array<string, mixed> $meta */
    final public function __construct(string $message, private readonly array $meta = [])
    {
        parent::__construct($message);
    }

    abstract public function problemStatus(): int;

    abstract public function problemCode(): string;

    abstract public function problemTitle(): string;

    public function problemMeta(): array
    {
        return $this->meta;
    }
}
