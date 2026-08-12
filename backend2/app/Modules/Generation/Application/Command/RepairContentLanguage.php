<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * Sweep the reachable content for learner-language strings that are not in the learner's language
 * and repair them.
 *
 * `apply` false is the default a caller should reach for first: the sweep reads the whole content
 * set and the repairs cost model calls, so seeing the list before paying for it is the sane order.
 */
final readonly class RepairContentLanguage
{
    public function __construct(
        public LanguageCode $sourceLang,
        public bool $apply = false,
    ) {}
}
