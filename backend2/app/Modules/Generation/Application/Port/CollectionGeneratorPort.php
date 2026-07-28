<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GenerationBrief;

/**
 * The seam to the LLM. Nothing outside Infrastructure/Adapter knows which model or vendor
 * is behind it; tests bind a deterministic fake. Implementations must return a draft that
 * matches the schema or throw — never partial data.
 */
interface CollectionGeneratorPort
{
    public function generate(GenerationBrief $brief): GeneratedCollectionDraft;
}
