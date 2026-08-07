<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Dto\RealtimeToken;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * Deterministic realtime-token minter — no network. Expires the token exactly TTL seconds after the
 * clock's now, so a test can assert that the configured TTL reached the mint. Records the last spec
 * so tests can inspect the model, voice and lesson the session would have been briefed with.
 */
final class FakeRealtimeTokenMinter implements RealtimeTokenPort
{
    public ?RealtimeSessionSpec $lastSpec = null;

    public function __construct(private readonly Clock $clock) {}

    public function mint(RealtimeSessionSpec $spec): RealtimeToken
    {
        $this->lastSpec = $spec;

        return new RealtimeToken(
            value: 'fake-ephemeral-' . substr(md5($spec->model . '|' . $spec->ttlSeconds), 0, 12),
            expiresAt: $this->clock->now()->modify("+{$spec->ttlSeconds} seconds"),
            model: $spec->model,
        );
    }
}
