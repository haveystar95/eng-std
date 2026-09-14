<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\VoiceScene;
use App\Modules\Plan\Application\Command\VoiceSceneHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything one scene says out loud, in its two voices (DAY-UI-3) — at most four vendor calls.
 *
 * THE VENDOR'S LIMIT IS WAITED OUT, NOT FOUGHT. Gemini TTS on the free tier answers 429 per MINUTE (10
 * requests) and per DAY (100 per model); the answer names which and how long until its window opens
 * again. The job goes back on the queue for exactly that long — a minute, or until the vendor's
 * midnight — and every later attempt buys only what is still missing; nothing fails, and meanwhile
 * the phone reads the lines with its own voice. A day and a quarter is the most it waits.
 */
final class VoiceSceneJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /** A per-minute refusal with no delay named waits this long. */
    private const MINUTE_WAIT = 65;

    /** The longest wait a refusal can ask for — a daily window is under a day. */
    private const LONGEST_WAIT = 26 * 3600;

    public function __construct(private readonly string $sceneId) {}

    public function retryUntil(): DateTimeInterface
    {
        return new DateTimeImmutable('+30 hours');
    }

    public function handle(VoiceSceneHandler $handler): void
    {
        try {
            $handler(new VoiceScene(PlanSceneId::fromString($this->sceneId)));
        } catch (TransientSpeechError $e) {
            $wait = self::waitFor($e);
            Log::info('VoiceSceneJob waits for the vendor', ['scene_id' => $this->sceneId, 'seconds' => $wait, 'per_day' => $e->perDay, 'error' => $e->getMessage()]);
            $this->release($wait);
        }
    }

    /** How long the vendor asked to be left alone, a little jitter so waiting scenes do not all knock at once. */
    public static function waitFor(TransientSpeechError $e): int
    {
        $asked = $e->retryAfterSeconds ?? ($e->perDay ? 3600 : self::MINUTE_WAIT);

        return min(self::LONGEST_WAIT, max(self::MINUTE_WAIT, $asked)) + random_int(0, 20);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('VoiceSceneJob failed; lines keep the phone voice until plan:speak-backfill', ['scene_id' => $this->sceneId, 'error' => $e->getMessage()]);
    }
}
