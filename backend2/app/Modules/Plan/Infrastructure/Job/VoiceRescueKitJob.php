<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\VoiceRescueKit;
use App\Modules\Plan\Application\Command\VoiceRescueKitHandler;
use App\Modules\Plan\Application\Exception\VoiceCapReached;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The rescue kit of a plan said in its learner's voice (наряд LANG-1b §2) — six short lines, each on a call of its own. The
 * plan never waits for it: a line without its sound is read by the phone. It ends the way a scene's voice ends
 * ({@see VoiceSceneJob}): a transient refusal puts it back on the queue, a refusal of the account fails it, the cap or the
 * fuse stops it with a letter in the log.
 */
final class VoiceRescueKitJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(private readonly string $planId) {}

    public function retryUntil(): DateTimeInterface
    {
        return new DateTimeImmutable('+6 hours');
    }

    public function handle(VoiceRescueKitHandler $handler): void
    {
        try {
            $handler(new VoiceRescueKit(PlanId::fromString($this->planId)));
        } catch (TransientSpeechError $e) {
            $wait = VoiceSceneJob::waitFor($e);
            Log::info('VoiceRescueKitJob waits for the vendor', ['plan_id' => $this->planId, 'seconds' => $wait, 'error' => $e->getMessage()]);
            $this->release($wait);
        } catch (SpeechAccountError $e) {
            Log::error('VoiceRescueKitJob failed: the voice vendor account refused ('.$e->vendorCode.'); the kit reads with the phone voice', [
                'plan_id' => $this->planId, 'code' => $e->vendorCode, 'status' => $e->httpStatus, 'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        } catch (VoiceCapReached|VoiceFuseTripped $e) {
            Log::error('VoiceRescueKitJob stopped by the credits cap or the voice fuse; nothing bought', ['plan_id' => $this->planId, 'error' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('VoiceRescueKitJob failed; the kit keeps the phone voice', ['plan_id' => $this->planId, 'error' => $e->getMessage()]);
    }
}
