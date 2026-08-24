<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Service;

use App\Modules\Collections\Domain\ValueObject\LanguagePair;
use App\Modules\Identity\Application\Port\DefaultTargetLangReader;
use App\Modules\Identity\Application\Port\NativeLangReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The pair a NEW collection gets when nobody named one — and the ONLY thing the profile still
 * decides about language (DECISIONS п. 142).
 *
 * This is the whole of «настройки Source/Target Language — дефолт при создании» (п. 81). Once the
 * collection exists it carries its own pair and every reader takes it from there; changing the
 * profile afterwards renames nothing and re-languages nothing.
 *
 * Before this, the defaults were `ru`/`en` literals in three different places — the HTTP
 * controller, the «Сохранённые» command's constructor and the store controller — so a learner whose
 * profile said `uk` still got Russian folders, and the profile setting they had just changed did
 * nothing at all.
 */
final readonly class DefaultCollectionPair
{
    /** What the app teaches when the user has no profile row yet. */
    public const STUDIED_FALLBACK = 'en';

    public function __construct(
        private NativeLangReader $native,
        private DefaultTargetLangReader $target,
    ) {}

    public function forOwner(UserId $ownerId): LanguagePair
    {
        return new LanguagePair(
            targetLang: $this->target->defaultTargetLangFor($ownerId) ?? new LanguageCode(self::STUDIED_FALLBACK),
            sourceLang: $this->native->nativeLangFor($ownerId) ?? new LanguageCode(NativeLangReader::FALLBACK),
        );
    }
}
