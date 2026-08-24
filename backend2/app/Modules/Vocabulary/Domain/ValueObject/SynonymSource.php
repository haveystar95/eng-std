<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

/**
 * Who put a synonym on a term. Mirrors the CHECK on `term_synonyms.source`.
 *
 * The distinction is not bookkeeping: a re-run of the станок must be able to leave alone what a
 * person decided. `auto` rows are the model's proposal and may be replaced by a later version;
 * `curated` rows are somebody's judgement and outrank it — the same hierarchy the registry states
 * for translations («ручной ввод юзера = его правда»).
 */
enum SynonymSource: string
{
    case Auto = 'auto';
    case Curated = 'curated';
}
