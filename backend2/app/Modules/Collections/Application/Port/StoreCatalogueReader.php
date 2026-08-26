<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\StoreCatalogueSummary;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The store, summarised: how many subscribable decks this learner does not have yet, and a few of
 * their titles. Sibling of {@see StoreCollectionsReader}, which pages the same catalogue for the
 * store screen — this one answers the two questions the HOME screen asks about it and stops there.
 */
interface StoreCatalogueReader
{
    public function summaryFor(
        UserId $viewer,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        int $sampleSize,
    ): StoreCatalogueSummary;
}
