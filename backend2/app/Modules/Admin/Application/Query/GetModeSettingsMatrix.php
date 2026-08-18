<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/** Read the «Матрица режимов» screen's rows: global only, or merged with one user's overrides. */
final readonly class GetModeSettingsMatrix
{
    public function __construct(public ?UserId $userId = null) {}
}
