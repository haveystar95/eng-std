<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One trainer's line in the passport's simulation: could a card be built from THIS term's content,
 * and if not, why.
 *
 * An Admin DTO rather than Learning's own view object, so the JSON mapper (Presentation) never has
 * to import another module's Application — the translation happens once, in the query handler, which
 * is the layer allowed the cross-module read.
 *
 * `status` is `ok` | `blocked` | `pool_dependent`. The third is not a hedge: `multiple_choice`
 * builds its wrong options out of OTHER words in the session, so no content of this term decides it.
 */
final readonly class ModeSimulationRow
{
    public function __construct(
        public string $mode,
        public string $status,
        public ?string $reason,
        public string $explanation,
    ) {}
}
