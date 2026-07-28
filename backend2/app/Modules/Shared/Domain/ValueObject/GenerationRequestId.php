<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/** Cross-cutting id: collections may reference the generation that produced them. */
final class GenerationRequestId extends Identifier {}
