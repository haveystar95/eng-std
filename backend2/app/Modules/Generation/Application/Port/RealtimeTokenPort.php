<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Dto\RealtimeToken;

/**
 * Mints a short-lived ephemeral client secret for a realtime voice session, briefed with the
 * lesson's instructions. The audio then flows client ↔ OpenAI directly (WebRTC) — it never
 * transits our server. The real API key stays server-side and is never returned to the client.
 */
interface RealtimeTokenPort
{
    public function mint(RealtimeSessionSpec $spec): RealtimeToken;
}
