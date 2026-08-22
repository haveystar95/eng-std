<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The dev sign-in door is shut ({@see \App\Modules\Identity\Domain\Service\DevLoginGate}).
 *
 * 404 and not 403, deliberately: a closed door should not admit that it is a door. The route is
 * not even registered when the gate is shut, so this is the SECOND lock — the one that answers
 * when something else (a cached route table, a re-registration, a future refactor) leaves the path
 * reachable after the gate closed.
 */
final class DevLoginUnavailable extends DomainException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('Not found.');
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'not_found';
    }

    public function problemTitle(): string
    {
        return 'Not Found';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
