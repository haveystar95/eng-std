<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Read the trainer toggles for the panel: global only, or as one user sees them. */
final readonly class GetExerciseModes
{
    public function __construct(public ?UserId $userId = null) {}
}
