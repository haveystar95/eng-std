<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Exception\GenerationQuotaExceeded;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Domain\Service\PromptNormalizer;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

/**
 * Enforces the daily quota, records the request as pending, and returns its id. It does NOT
 * run the model — the caller either dispatches the job (HTTP) or runs ProcessGeneration
 * inline (console). Quota counts today's non-failed requests, so a failure refunds itself.
 */
final readonly class RequestCollectionGenerationHandler
{
    public const PROMPT_VERSION = 'v3';
    public const DAILY_LIMIT = 50;

    public function __construct(
        private GenerationRequestRepository $requests,
        private GenerationQuota $quota,
        private PromptNormalizer $normalizer,
        private Clock $clock,
    ) {}

    public function __invoke(RequestCollectionGeneration $command): GenerationRequestId
    {
        $now = $this->clock->now();

        if ($this->quota->usedOn($command->userId, $now) >= self::DAILY_LIMIT) {
            throw GenerationQuotaExceeded::perDay(self::DAILY_LIMIT);
        }

        $request = GenerationRequest::open(
            id: GenerationRequestId::generate(),
            userId: $command->userId,
            prompt: $command->prompt,
            normalizedPrompt: $this->normalizer->normalize($command->prompt),
            sourceLang: $command->sourceLang,
            targetLang: $command->targetLang,
            levels: $command->levels,
            size: $command->size,
            promptVersion: self::PROMPT_VERSION,
            createdAt: $now,
        );

        $this->requests->save($request);

        return $request->id();
    }
}
