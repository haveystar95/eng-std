<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Port\DefaultTargetLangReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class EloquentDefaultTargetLangReader implements DefaultTargetLangReader
{
    public function defaultTargetLangFor(UserId $userId): ?LanguageCode
    {
        $lang = Profile::query()
            ->where('user_id', $userId->value)
            ->value('target_language');

        return $lang !== null && $lang !== '' ? new LanguageCode((string) $lang) : null;
    }
}
