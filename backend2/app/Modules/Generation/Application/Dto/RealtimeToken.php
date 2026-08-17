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
    /**
     * @param array<string, mixed>|null $sessionSetup  the setup the client must apply on connect,
     *        pre-rendered by the server (Gemini bare-token path). Null when the session config is
     *        already baked into the token (OpenAI, or a constrained Gemini token).
     */
    public function __construct(
        public string $value,
        public DateTimeImmutable $expiresAt,
        public string $model,
        public string $provider,
        public string $endpoint,
        public ?array $sessionSetup = null,
    ) {}
}
