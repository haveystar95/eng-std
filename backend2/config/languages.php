<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | The fixed side of every language pair
    |--------------------------------------------------------------------------
    |
    | What the app TEACHES. `SupportedLanguages::supports()` still requires this
    | code on exactly one side of every pair, so the search pill reads «EN → X»
    | or «X → EN».
    |
    | That requirement is a TEMPORARY v1 limit, not the rule. The decision of
    | 2026-08-24 is that PAIRS ARE ARBITRARY — any taught language × any support
    | language, with no obligatory English on either side (docs/DECISIONS.md
    | п. 134) — and the schema has always allowed it: `terms.lang` and
    | `term_translations.lang` are real columns, and the live database already
    | holds Polish terms with Russian translations. What has to move before the
    | limit comes off is in docs/search-language-pair.md (RS-3).
    |
    */
    'target' => env('APP_TARGET_LANG', 'en'),

    /*
    |--------------------------------------------------------------------------
    | The languages a learner may read
    |--------------------------------------------------------------------------
    |
    | The other side of the pair, and the list the search pill offers. A CONFIG
    | and not a constant because this list grows by deployment rather than by
    | release — adding a language is an env change plus content, not a code
    | change.
    |
    | Adding one is not free: see the findings doc above. A language the
    | LanguagePurity check has no opinion on passes the content barrier
    | unscreened, and one the prompt adapters do not name reaches the model as a
    | bare ISO code.
    |
    */
    'natives' => array_values(array_filter(array_map(
        static fn (string $code): string => strtolower(trim($code)),
        explode(',', (string) env('APP_NATIVE_LANGS', 'ru,ro')),
    ))),
];
