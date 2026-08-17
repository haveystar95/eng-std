<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\StoreCollectionPage;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The public store: system + public collections for a language pair, ordered by topic so the
 * client can render sections, cursor-paginated. Marks each row with whether the given user is
 * already subscribed.
 */
interface StoreCollectionsReader
{
    public function forLanguagePair(
        UserId $viewer,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        ?string $cursor,
        int $limit,
    ): StoreCollectionPage;
}
