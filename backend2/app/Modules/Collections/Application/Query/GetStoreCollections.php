<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class GetStoreCollections
{
    public function __construct(
        public UserId $viewer,
        public LanguageCode $sourceLang,
        public LanguageCode $targetLang,
        public ?string $cursor = null,
        public int $limit = 30,
    ) {}
}
