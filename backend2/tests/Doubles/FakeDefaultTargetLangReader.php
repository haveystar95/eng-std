<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Identity\Application\Port\DefaultTargetLangReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class FakeDefaultTargetLangReader implements DefaultTargetLangReader
{
    public function __construct(private readonly ?string $lang = null) {}

    public function defaultTargetLangFor(UserId $userId): ?LanguageCode
    {
        return $this->lang !== null ? new LanguageCode($this->lang) : null;
    }
}
