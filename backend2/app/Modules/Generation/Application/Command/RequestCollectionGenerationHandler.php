<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\GenerationRequestOutcome;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Exception\GenerationIdConflict;
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
    /**
     * The prompt file the model is given, and part of the prompt-cache key — so a bump here is also
     * a deliberate cache miss: the next identical prompt is regenerated rather than served the set
     * the previous version produced. That is the point of bumping rather than editing v5 in place.
     */
    public const PROMPT_VERSION = 'v9';

    public function __construct(
        private GenerationRequestRepository $requests,
        private GenerationQuota $quota,
        private PromptNormalizer $normalizer,
        private Clock $clock,
        private UserTierReader $tiers,
        private GenerationDailyLimit $limits,
        private DefaultTargetLangReader $defaultTargetLang,
    ) {}

    public function __invoke(RequestCollectionGeneration $command): GenerationRequestOutcome
    {
        // Idempotency: a client-supplied id that already exists returns the existing request —
        // no new row, no second job, no quota spent (an offline retry of the same prompt).
        if ($command->id !== null) {
            $existing = $this->requests->findById($command->id);
            if ($existing !== null) {
                if (! $existing->userId()->equals($command->userId)) {
                    throw GenerationIdConflict::make();
                }

                return new GenerationRequestOutcome($existing->id(), created: false);
            }
        }

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
            id: $command->id ?? GenerationRequestId::generate(),
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

        return new GenerationRequestOutcome($request->id(), created: true);
    }
}
