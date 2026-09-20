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
}
