<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Identity\Application\Port\DefaultTargetLangReader;
use App\Modules\Identity\Application\Port\NativeLangReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The learner's language PAIR, resolved once with its fallbacks stated in one place.
 *
 * Search asks this question three times — the free query, the paid lookup, the term it creates —
 * and all three must get the same answer or a word is looked up in one language pair and cached
 * under another. That is not hypothetical: the cache key contains the pair.
 */
final readonly class LearnerLanguages
{
    /** What the app teaches when a user has no profile row yet. */
    public const TARGET_FALLBACK = 'en';

    public function __construct(
        private NativeLangReader $native,
        private DefaultTargetLangReader $target,
    ) {}

    public function forUser(UserId $userId): LanguagePair
    {
        return new LanguagePair(
            native: $this->native->nativeLangFor($userId) ?? new LanguageCode(NativeLangReader::FALLBACK),
            target: $this->target->defaultTargetLangFor($userId) ?? new LanguageCode(self::TARGET_FALLBACK),
        );
    }
}
