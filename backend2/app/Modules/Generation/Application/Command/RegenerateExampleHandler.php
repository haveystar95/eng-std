<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\ExampleRegenBrief;
use App\Modules\Generation\Application\Dto\RegeneratedExample;
use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;
use App\Modules\Generation\Application\Port\GenerationQuota;
use App\Modules\Generation\Application\Port\RecordsExampleRegeneration;
use App\Modules\Generation\Application\Service\ExampleReplacement;
use App\Modules\Generation\Domain\Exception\GenerationQuotaExceeded;
use App\Modules\Generation\Domain\Service\GenerationDailyLimit;
use App\Modules\Shared\Domain\Service\ModelCost;
use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Query\ExampleRegenContextReader;

/**
 * Generates a fresh example for a term, avoiding the current one, and swaps it in. Rate-limited by
 * the same daily generation quota as collection generation — recording the regeneration is what
 * consumes a unit (the quota reader counts these rows too). Returns null when the term is unknown
 * (the controller maps that to 404).
 *
 * Note (single-user product): term_examples are global, so this replaces the shared example — fine
 * here; a multi-user build would need per-user example overrides.
 */
final readonly class RegenerateExampleHandler
{
    /** Native-language fallback for the example's translation when the term has no translation yet. */
    private const DEFAULT_TRANSLATION_LANG = 'ru';

    public function __construct(
        private ExampleRegenContextReader $context,
        private ExampleRegeneratorPort $regenerator,
        private ExampleReplacement $replaceExample,
        private RecordsExampleRegeneration $spend,
        private ModelCost $cost,
        private GenerationQuota $quota,
        private UserTierReader $tiers,
        private GenerationDailyLimit $limits,
        private Clock $clock,
    ) {}

    public function __invoke(RegenerateExample $command): ?RegeneratedExample
    {
        $ctx = $this->context->find($command->termId);
        if ($ctx === null) {
            return null;
        }

        $now = $this->clock->now();
        $limit = $this->limits->forTier($this->tiers->tierOf($command->userId));
        if ($this->quota->usedOn($command->userId, $now) >= $limit) {
            throw GenerationQuotaExceeded::perDay($limit);
        }

        $result = $this->regenerator->regenerate(new ExampleRegenBrief(
            text: $ctx->text,
            termLang: new LanguageCode($ctx->lang),
            translationLang: new LanguageCode($ctx->translationLang ?? self::DEFAULT_TRANSLATION_LANG),
            avoid: $ctx->currentExample,
        ));

        $this->replaceExample->apply(
            $command->termId,
            $result->example,
            $result->exampleTranslation,
            $result->promptVersion,
            $result->model,
        );

        $this->spend->record(
            $command->userId,
            $command->termId,
            $result->model,
            $result->tokensIn,
            $result->tokensOut,
            $this->cost->estimate($result->model, $result->tokensIn, $result->tokensOut),
            $now,
        );

        return new RegeneratedExample($result->example, $result->exampleTranslation);
    }
}
