<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Shared\Domain\ValueObject\Identifier;

/** Client-generated ULID. Makes review uploads idempotent (ON CONFLICT DO NOTHING). */
final class ReviewId extends Identifier {}
