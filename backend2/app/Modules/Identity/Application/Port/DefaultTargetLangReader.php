<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The user's default learning language (profiles.target_language) — what generation should target
 * when a request doesn't name one. Null when the user has no profile yet (caller falls back to en).
 */
interface DefaultTargetLangReader
{
    public function defaultTargetLangFor(UserId $userId): ?LanguageCode;
}
