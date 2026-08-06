<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Exception\GenerationQuotaExceeded;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
use App\Modules\Generation\Domain\Service\PromptNormalizer;
use App\Modules\Identity\Application\Port\DefaultTargetLangReader;
use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * Enforces the daily quota, records the request as pending, and returns its id. It does NOT
 * run the model — the caller either dispatches the job (HTTP) or runs ProcessGeneration
 * inline (console). Quota counts today's non-failed requests, so a failure refunds itself.
 * The daily limit comes from the user's tier ({@see GenerationDailyLimit}).
 */
final readonly class RequestCollectionGenerationHandler
{
    public const PROMPT_VERSION = 'v4';

    public function __construct(
        private GenerationRequestRepository $requests,
        private GenerationQuota $quota,
        private PromptNormalizer $normalizer,
        private Clock $clock,
        private UserTierReader $tiers,
        private GenerationDailyLimit $limits,
        private DefaultTargetLangReader $defaultTargetLang,
    ) {}

    public function __invoke(RequestCollectionGeneration $command): GenerationRequestId
    {
        $now = $this->clock->now();

        $limit = $this->limits->forTier($this->tiers->tierOf($command->userId));
        if ($this->quota->usedOn($command->userId, $now) >= $limit) {
            throw GenerationQuotaExceeded::perDay($limit);
        }

        // No target language on the request → the user's default learning language, then English.
        $targetLang = $command->targetLang
            ?? $this->defaultTargetLang->defaultTargetLangFor($command->userId)
            ?? new LanguageCode('en');

        $request = GenerationRequest::open(
            id: GenerationRequestId::generate(),
            userId: $command->userId,
            prompt: $command->prompt,
            normalizedPrompt: $this->normalizer->normalize($command->prompt),
            sourceLang: $command->sourceLang,
            targetLang: $targetLang,
            levels: $command->levels,
            size: $command->size,
            promptVersion: self::PROMPT_VERSION,
            createdAt: $now,
        );

        $this->requests->save($request);

        return $request->id();
    }
}
