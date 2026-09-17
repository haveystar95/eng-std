<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Dto\PlaygroundRunRequest;
use App\Modules\Generation\Application\Service\PlaygroundRuns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The call of one sandbox run (наряд GEN-3). ONE try: a sandbox call is a person's experiment, and a queue retry would buy
 * it twice; a call that got no answer is in the journal as `lost`, and trying again is the person's decision. Its timeout
 * outlives the call's own wait for the answer by a minute.
 */
final class RunPlaygroundCallJob implements ShouldQueue
{
    use Queueable;

    private const MARGIN_SECONDS = 60;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        private readonly string $runId,
        private readonly string $provider,
        private readonly string $model,
        private readonly string $prompt,
        private readonly ?float $temperature,
    ) {
        $this->timeout = (int) config('playground.timeout') + self::MARGIN_SECONDS;
    }

    public function handle(PlaygroundRuns $runs): void
    {
        $runs->run(new PlaygroundRunRequest($this->runId, $this->provider, $this->model, $this->prompt, $this->temperature));
    }

    public function failed(Throwable $e): void
    {
        Log::error('RunPlaygroundCallJob failed', ['run_id' => $this->runId, 'error' => mb_substr($e->getMessage(), 0, 300)]);
    }
}
