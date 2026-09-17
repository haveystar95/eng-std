<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * ONE SANDBOX RUN (наряд GEN-3): the prompt is sent by a queued job, and the screen polls the run until it is done — no
 * browser request waits on a model that takes a minute to write a lesson. `queued` — the job is not picked up yet;
 * `running` — the call is out; `done` — `answer` holds what came back, a vendor's failure included (the sandbox answers
 * with text, never with a 500).
 */
final readonly class PlaygroundRun
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public function __construct(
        public string $id,
        public string $status,
        public string $provider,
        public string $model,
        public ?PlaygroundAnswer $answer = null,
    ) {}

    public function running(): self
    {
        return new self($this->id, self::RUNNING, $this->provider, $this->model);
    }

    public function done(PlaygroundAnswer $answer): self
    {
        return new self($this->id, self::DONE, $this->provider, $this->model, $answer);
    }
}
