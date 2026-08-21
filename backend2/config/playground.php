<?php

declare(strict_types=1);

/**
 * The admin «Песочница» model registry — CONFIG, never a table.
 *
 * Two rules shaped this file:
 *
 *  - **The OpenAI list is derived, not typed out.** It is exactly the models this project already
 *    runs (`services.generation.*`, `services.openai.*`), read from the same keys production reads.
 *    A hand-kept second list would drift the first time a model is bumped, and the sandbox would
 *    then be experimenting with a model nothing else uses — which is the opposite of its purpose.
 *  - **No version is hard-coded for Anthropic.** The alias `claude-haiku-4-5` is a moving pointer
 *    the vendor maintains; pin a dated id through `ANTHROPIC_PLAYGROUND_MODEL` when a run has to be
 *    reproducible. The alias is also what `ModelCost` prices, so a call made through it is costed
 *    rather than reported unpriced.
 *
 * Availability is a runtime fact (is the key set), answered by the catalogue — a provider with no
 * key is *unavailable*, never an error, exactly as in the bake-off catalogue next to it.
 */
return [
    /**
     * Per-call timeout for the sandbox, seconds. Deliberately far below the 180s the production
     * generation path allows itself: nobody sits in front of a screen for three minutes, and a
     * sandbox call that hangs is a browser tab that looks broken.
     */
    'timeout' => (int) env('PLAYGROUND_TIMEOUT', 60),

    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'env' => 'OPENAI_API_KEY',
            'base_url' => 'https://api.openai.com/v1',
            // The models the project actually uses, in the order someone reaches for them: the core
            // generator, the mechanics/enrichment model, then whatever production and the comparison
            // are pointed at. Duplicates and blanks are dropped by the catalogue.
            'models' => [
                env('GENERATION_CORE_MODEL', 'gpt-5.4'),
                env('GENERATION_MECHANICS_MODEL', 'gpt-4o-mini'),
                env('OPENAI_GENERATE_MODEL', 'gpt-4o'),
                env('OPENAI_ENRICH_MODEL', 'gpt-4o-mini'),
                env('OPENAI_SUMMARY_MODEL', 'gpt-4o-mini'),
                env('OPENAI_COMPARE_MODEL'),
            ],
        ],

        'anthropic' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'env' => 'ANTHROPIC_API_KEY',
            'base_url' => 'https://api.anthropic.com/v1',
            'models' => [
                // Haiku first: the sandbox is for trying prompts, and the cheap fast model is the
                // one to try them on.
                env('ANTHROPIC_PLAYGROUND_MODEL', 'claude-haiku-4-5'),
                env('ANTHROPIC_GENERATE_MODEL', 'claude-opus-5'),
            ],
        ],
    ],
];
