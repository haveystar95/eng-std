<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\PlaygroundRun;
use App\Modules\Generation\Application\Dto\PlaygroundRunRequest;
use App\Modules\Generation\Application\Port\PlaygroundRunDispatcher;
use App\Modules\Generation\Application\Port\PlaygroundRunStore;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * THE SANDBOX, ASYNCHRONOUS (наряд GEN-3): starting a run stores it `queued` and queues its call; the job marks it `running`,
 * makes the one call ({@see PlaygroundCall} — verbatim, priced, failures as text) and stores the answer `done`; the screen
 * polls {@see find()}. A prompt the size of a lesson takes a strong model 30–51 s — longer than a browser request should
 * hang, and the 60 s the synchronous sandbox allowed cut such a call off after the vendor had billed it.
 */
final readonly class PlaygroundRuns
{
    public function __construct(
        private PlaygroundCall $call,
        private PlaygroundRunStore $runs,
        private PlaygroundRunDispatcher $dispatcher,
    ) {}

    /** @return string the run's id, to poll */
    public function start(string $provider, string $model, string $prompt, ?float $temperature): string
    {
        $id = Ulid::generate();
        $this->runs->save(new PlaygroundRun($id, PlaygroundRun::QUEUED, $provider, $model));
        $this->dispatcher->dispatch(new PlaygroundRunRequest($id, $provider, $model, $prompt, $temperature));

        return $id;
    }

    /** The job's work: the call, and its answer stored on the run. */
    public function run(PlaygroundRunRequest $request): void
    {
        $run = $this->runs->find($request->runId) ?? new PlaygroundRun($request->runId, PlaygroundRun::QUEUED, $request->provider, $request->model);
        $this->runs->save($run->running());
        $this->runs->save($run->done($this->call->run($request->provider, $request->model, $request->prompt, $request->temperature)));
    }

    public function find(string $id): ?PlaygroundRun
    {
        return $this->runs->find($id);
    }
}
