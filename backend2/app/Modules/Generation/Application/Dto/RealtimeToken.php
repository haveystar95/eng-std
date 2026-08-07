<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/**
 * The ephemeral credential handed to the app, plus everything it needs to connect: which provider
 * ('openai' | 'gemini'), the connection endpoint, the bound model, and when the token expires.
 */
final readonly class RealtimeToken
{
    public function __construct(
        public string $value,
        public DateTimeImmutable $expiresAt,
        public string $model,
        public string $provider,
        public string $endpoint,
    ) {}
}
