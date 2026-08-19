<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\BakeoffTrack;
use App\Modules\Generation\Domain\ValueObject\CheckedBatch;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * One provider's answer to one task: what it produced, what the checks made of it, and what it cost.
 *
 * A FAILED call is a first-class result rather than an exception that ends the run. A provider that
 * dies on a third of the sample has said something about itself, and a bake-off that aborted on the
 * first timeout would report the survivors as if nothing had happened.
 */
final readonly class BakeoffCallResult
{
    private function __construct(
        public BakeoffTrack $track,
        public ProviderId $provider,
        public string $model,
        public string $taskKey,
        public string $promptSha,
        public bool $ok,
        public ?CheckedBatch $batch,
        public ?int $latencyMs,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public ?string $error,
        public string $raw = '',
    ) {}

    public static function answered(
        BakeoffTrack $track,
        ProviderId $provider,
        string $model,
        string $taskKey,
        string $promptSha,
        CheckedBatch $batch,
        ModelAnswer $answer,
    ): self {
        return new self(
            $track, $provider, $model, $taskKey, $promptSha, true, $batch,
            $answer->latencyMs, $answer->tokensIn, $answer->tokensOut, $answer->costUsd, null, $answer->raw,
        );
    }

    public static function failed(
        BakeoffTrack $track,
        ProviderId $provider,
        string $model,
        string $taskKey,
        string $promptSha,
        string $error,
        ?int $latencyMs = null,
    ): self {
        return new self(
            $track, $provider, $model, $taskKey, $promptSha, false, null,
            $latencyMs, null, null, null, $error,
        );
    }
}
