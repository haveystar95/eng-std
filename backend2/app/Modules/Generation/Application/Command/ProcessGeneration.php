<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

/** Run a pending generation: call the model, validate, materialize terms + collection. */
final readonly class ProcessGeneration
{
    public function __construct(public GenerationRequestId $id) {}
}
