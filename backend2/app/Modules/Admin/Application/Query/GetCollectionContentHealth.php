<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/** One collection's terms, judged by what a card can be built from each. */
final readonly class GetCollectionContentHealth
{
    public function __construct(public string $collectionId) {}
}
