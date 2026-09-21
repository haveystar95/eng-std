<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * WHAT ONE TURN COST AND HOW LONG IT TOOK (наряд CONV-1, п. 5–6): the model and the voice are two
 * purchases on one move, and the learner waits for both — so they are counted together on the turn
 * and separately inside it, which is the only way to answer «что именно держит шесть секунд».
 */
final readonly class TurnCost
{
    public function __construct(
        public string $modelCostUsd = '0.000000',
        public string $speechCostUsd = '0.000000',
        public int $modelLatencyMs = 0,
        public int $speechLatencyMs = 0,
        public int $latencyMs = 0,
        public ?string $model = null,
        public ?string $promptVersion = null,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
    ) {}

    /** The money of the turn — what the conversation's cap counts. */
    public function totalUsd(): string
    {
        return ModelCall::addCosts($this->modelCostUsd, $this->speechCostUsd);
    }

    /**
     * Two model calls on one move — the answer the server refused and the one it asked for after it (наряд CONV-2):
     * the learner waited for both and the plan paid for both, so the turn carries the sum. The model and the prompt are
     * the second call's.
     */
    public function plusModelCall(self $second): self
    {
        return new self(
            modelCostUsd: ModelCall::addCosts($this->modelCostUsd, $second->modelCostUsd),
            speechCostUsd: $this->speechCostUsd,
            modelLatencyMs: $this->modelLatencyMs + $second->modelLatencyMs,
            speechLatencyMs: $this->speechLatencyMs,
            latencyMs: $this->latencyMs + $second->modelLatencyMs,
            model: $second->model ?? $this->model,
            promptVersion: $second->promptVersion ?? $this->promptVersion,
            tokensIn: $this->tokensIn === null && $second->tokensIn === null ? null : (int) $this->tokensIn + (int) $second->tokensIn,
            tokensOut: $this->tokensOut === null && $second->tokensOut === null ? null : (int) $this->tokensOut + (int) $second->tokensOut,
        );
    }
}
