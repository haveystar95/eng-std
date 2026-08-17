<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Port\NativeLangReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class EloquentNativeLangReader implements NativeLangReader
{
    public function nativeLangFor(UserId $userId): ?LanguageCode
    {
        $lang = Profile::query()
            ->where('user_id', $userId->value)
            ->value('native_language');

        return $lang !== null && $lang !== '' ? new LanguageCode((string) $lang) : null;
    }
}
