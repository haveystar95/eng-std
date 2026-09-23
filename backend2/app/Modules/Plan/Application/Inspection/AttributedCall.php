<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\BuildWindow;
use App\Modules\Plan\Application\Dto\Inspection\JournalModelCall;

/**
 * A journal call read as the plan's, with the window it fell in (наряд ADM-1). `certain` — no other plan's window overlaps
 * and no second window of this plan holds it: the call can only be this one's.
 */
final readonly class AttributedCall
{
    public function __construct(
        public JournalModelCall $call,
        public BuildWindow $window,
        public ?int $day,
        public int $inOtherWindows,
    ) {}

    public function certain(): bool
    {
        return $this->window->others === 0 && $this->inOtherWindows === 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $c = $this->call;

        return [
            'id' => $c->id,
            'purpose' => $c->purpose,
            'status' => $c->status,
            'provider' => $c->provider,
            'model' => $c->model,
            'answered_model' => $c->answeredModel,
            'started_at' => $c->startedAt->format(DATE_ATOM),
            'finished_at' => $c->finishedAt?->format(DATE_ATOM),
            'latency_ms' => $c->latencyMs,
            'estimated_tokens_in' => $c->estimatedTokensIn,
            'tokens_in' => $c->tokensIn,
            'cached_tokens' => $c->cachedTokens,
            'tokens_out' => $c->tokensOut,
            'cost_usd' => $c->costUsd,
            'http_status' => $c->httpStatus,
            'error' => $c->error,
            'log_id' => $c->logId,
            'day' => $this->day,
            'window' => ['kind' => $this->window->kind, 'subject_id' => $this->window->subjectId, 'from' => $this->window->from->format(DATE_ATOM), 'to' => $this->window->to->format(DATE_ATOM), 'others' => $this->window->others],
            'certain' => $this->certain(),
        ];
    }
}
