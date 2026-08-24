<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Identity\Application\Port\NativeLangReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** The sibling of {@see FakeDefaultTargetLangReader}: null stands for «no profile row yet». */
final class FakeNativeLangReader implements NativeLangReader
{
    public function __construct(private readonly ?string $lang = null) {}

    public function nativeLangFor(UserId $userId): ?LanguageCode
    {
        return $this->lang !== null ? new LanguageCode($this->lang) : null;
    }
}
