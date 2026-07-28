<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exception;

/**
 * A domain exception that knows how to present itself as an HTTP problem (RFC 7807).
 * One renderer in bootstrap turns any implementer into an `application/problem+json`
 * response, so a new domain error surfaces with a stable machine `code` the Flutter
 * client switches on — without touching a central mapping table or any controller.
 */
interface ProblemDetails
{
    public function problemStatus(): int;

    /** Stable, snake_case machine code the client switches on. */
    public function problemCode(): string;

    public function problemTitle(): string;

    /** @return array<string, mixed> */
    public function problemMeta(): array;
}
