<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Read the trainer toggles: the global default alone, or a user's picture of them. */
final readonly class GetExerciseModeSettings
{
    public function __construct(public ?UserId $userId = null) {}
}
