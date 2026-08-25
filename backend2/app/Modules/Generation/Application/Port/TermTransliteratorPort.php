<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\TermReadingBrief;
use App\Modules\Generation\Application\Dto\TermReadingResult;

/**
 * One term in, its reading hint out — the single-word door onto the product the core writes in bulk.
 *
 * A port of its own rather than a second use of {@see CollectionGeneratorPort} or of the станок's
 * packer: those two speak about collections and about machinery, and a call that produces ONE field
 * of ONE term would have to lie about being one of them. Anything that fails — transport, a refusal,
 * a reply that is not the requested shape — throws; whether that costs the caller anything is the
 * caller's decision, and for a reading hint the answer is always no.
 */
interface TermTransliteratorPort
{
    public function read(TermReadingBrief $brief): TermReadingResult;
}
