<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\VoiceScene;
use App\Modules\Plan\Application\Command\VoiceSceneHandler;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything one scene says out loud, in its two voices (DAY-UI-3, TTS-2): the dialogue in one call, every phrase,
 * filler and word on a call of its own. The day never waits for it — the phone reads meanwhile.
 *
 * Three ways it ends without the voice, each for its reason:
 *
 * - the vendor's concurrency limit or a 5xx outlasts the adapter's own short retries — the job goes back on the queue
 *   for a while and buys only what is still missing (a transient refusal never fails a day);
 * - the vendor ACCOUNT refuses — no credits, a voice the plan does not include, a wrong key: the job FAILS with the
 *   vendor's code and the letter goes to the log; a retry would buy the same refusal. `plan:speak-backfill` after the
 *   account is fixed;
 * - the fuse tripped — too little of the account left: the letter goes to the log and the job ends without buying.
 */
final class VoiceSceneJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /** A transient refusal the vendor named no window for waits this long. */
    private const WAIT = 30;

    /** The longest a refusal may ask the job to wait. */
    private const LONGEST_WAIT = 3600;

    public function __construct(private readonly string $sceneId) {}

    public function retryUntil(): DateTimeInterface
    {
        return new DateTimeImmutable('+6 hours');
    }

    public function handle(VoiceSceneHandler $handler): void
    {
        try {
            $handler(new VoiceScene(PlanSceneId::fromString($this->sceneId)));
        } catch (TransientSpeechError $e) {
            $wait = self::waitFor($e);
            Log::info('VoiceSceneJob waits for the vendor', ['scene_id' => $this->sceneId, 'seconds' => $wait, 'error' => $e->getMessage()]);
            $this->release($wait);
        } catch (SpeechAccountError $e) {
            Log::error('VoiceSceneJob failed: the voice vendor account refused ('.$e->vendorCode.'); the day reads with the phone voice until plan:speak-backfill', [
                'scene_id' => $this->sceneId,
                'code' => $e->vendorCode,
                'status' => $e->httpStatus,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        } catch (VoiceFuseTripped $e) {
            Log::error('VoiceSceneJob stopped by the voice fuse; nothing bought, the day reads with the phone voice until plan:speak-backfill', [
                'scene_id' => $this->sceneId,
                'remaining' => $e->balance->remaining(),
                'limit' => $e->balance->limit,
                'source' => $e->balance->source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** How long the vendor asked to be left alone, a little jitter so waiting scenes do not all knock at once. */
    public static function waitFor(TransientSpeechError $e): int
    {
        return min(self::LONGEST_WAIT, max(self::WAIT, $e->retryAfterSeconds ?? self::WAIT)) + random_int(0, 10);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('VoiceSceneJob failed; lines keep the phone voice until plan:speak-backfill', ['scene_id' => $this->sceneId, 'error' => $e->getMessage()]);
    }
}
