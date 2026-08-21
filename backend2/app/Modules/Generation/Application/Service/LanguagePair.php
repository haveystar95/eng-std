<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/** What the learner speaks and what they are learning — always carried together. */
final readonly class LanguagePair
{
    public function __construct(
        public LanguageCode $native,
        public LanguageCode $target,
    ) {}
}
