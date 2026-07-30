<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** What the client submitted for one card: the response text, whether a hint was used, and how long it took. */
final readonly class Answer
{
    public function __construct(
        public string $response,
        public bool $usedHint = false,
        public ?int $latencyMs = null,
    ) {}
}
