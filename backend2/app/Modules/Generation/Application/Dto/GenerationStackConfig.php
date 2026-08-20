<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * Which generation stack this deployment is running, resolved once from config.
 *
 * There are two, and the second one replaces the first: `v1` is the frozen single-vendor adapter
 * (prompt v9, gpt-4o, OpenAI wire format inlined); `v2` is the multi-vendor stack — a prompt
 * assembled from the versioned catalogue, a schema shared with the bake-off, and any of the four
 * providers behind {@see \App\Modules\Generation\Application\Port\ContentModelPort}.
 *
 * It is a FLAG and not a deploy on purpose. The cut-over changes the model, the prompt and the
 * vendor path all at once, in the one subsystem whose failures are expensive and only visible in the
 * content days later. `GENERATION_STACK=v1` puts production back on the exact code that has been
 * serving it, without a build.
 *
 * The core and the machinery are configured separately because the A/B says they should be
 * different models: the core is written once and read forever (gpt-5.4), the machinery around it is
 * mechanical and 200× cheaper on the small model (gpt-4o-mini). One shared "the generation model"
 * setting is how that saving quietly disappears.
 */
final readonly class GenerationStackConfig
{
    public const LEGACY = 'v1';

    public const CONTENT_MODEL = 'v2';

    public function __construct(
        public string $stack,
        public string $corePromptVersion,
        public ProviderId $coreProvider,
        public string $coreModel,
        public string $mechanicsPromptVersion,
        public ProviderId $mechanicsProvider,
        public string $mechanicsModel,
    ) {}

    /** Is production still on the frozen single-vendor generator? */
    public function isLegacy(): bool
    {
        return $this->stack === self::LEGACY;
    }
}
