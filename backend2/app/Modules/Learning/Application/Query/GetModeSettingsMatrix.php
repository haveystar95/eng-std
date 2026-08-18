<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Read every row of the matrix in one scope, each tagged with where it comes from. */
final readonly class GetModeSettingsMatrix
{
    public function __construct(public ?UserId $userId = null) {}
}
