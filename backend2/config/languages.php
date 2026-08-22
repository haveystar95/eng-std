<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | The fixed side of every language pair
    |--------------------------------------------------------------------------
    |
    | What the app TEACHES. In v1 every pair has English on one side and one of
    | `natives` on the other, so the search pill is always «EN → X» or «X → EN».
    |
    | This is a v1 limit of the PRODUCT, not of the schema: `terms.lang` and
    | `term_translations.lang` are real columns and the live database already
    | holds Polish terms with Russian translations, so a pair without English is
    | representable the day it is wanted. See docs/search-language-pair.md for
    | what else would have to move.
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
