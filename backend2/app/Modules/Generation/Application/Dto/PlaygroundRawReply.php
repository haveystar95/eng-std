<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What a sandbox adapter got back, before anybody has an opinion about it.
 *
 * Deliberately NOT {@see ModelAnswer}: that one carries a decoded payload, because every production
 * caller asks for a schema and a reply that is not that schema is a failure. The sandbox asks for
 * nothing — the whole point is to send a prompt AS WRITTEN and see what comes back — so unparseable
 * text is a normal result here and must survive to the screen intact.
 */
final readonly class PlaygroundRawReply
{
    public function __construct(
        public string $rawText,
        /** What the vendor says it used, which is not always what was asked for. */
        public string $model,
        public int $latencyMs,
        public ?int $tokensIn,
        public ?int $tokensOut,
    ) {}
}
