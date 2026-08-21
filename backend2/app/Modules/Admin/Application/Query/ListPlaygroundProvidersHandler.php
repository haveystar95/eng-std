<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\PlaygroundProviderRow;
use App\Modules\Generation\Application\Dto\PlaygroundProvider;
use App\Modules\Generation\Application\Port\PlaygroundModelCatalog;

/**
 * Generation owns the registry; Admin re-shapes it for the panel. The mapping happens HERE and not
 * in the JSON layer because Presentation may not import another module's Application — the same
 * boundary every cross-module read in this module holds.
 */
final readonly class ListPlaygroundProvidersHandler
{
    public function __construct(private PlaygroundModelCatalog $catalog) {}

    /** @return list<PlaygroundProviderRow> */
    public function __invoke(ListPlaygroundProviders $query): array
    {
        return array_map(
            static fn (PlaygroundProvider $p): PlaygroundProviderRow => new PlaygroundProviderRow(
                provider: $p->provider,
                label: $p->label,
                models: $p->models,
                available: $p->available,
                reason: $p->reason,
            ),
            $this->catalog->providers(),
        );
    }
}
