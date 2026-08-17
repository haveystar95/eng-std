<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;

final readonly class GetCollectionTranslationLangHandler
{
    /** The only learner language the content actually has — the fallback when nothing is readable. */
    public const FALLBACK = 'ru';

    public function __construct(private GetCollectionTermSetHandler $termSet) {}

    public function __invoke(GetCollectionTranslationLang $query): string
    {
        foreach ($query->collectionIds as $collectionId) {
            $set = ($this->termSet)(new GetCollectionTermSet($collectionId));
            if ($set !== null) {
                return $set->sourceLang;
            }
        }

        return self::FALLBACK;
    }
}
