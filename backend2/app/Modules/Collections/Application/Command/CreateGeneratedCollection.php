<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Create an AI-sourced personal collection (used by the Generation module). */
final readonly class CreateGeneratedCollection
{
    public function __construct(
        public UserId $ownerId,
        public string $title,
        public LanguageCode $sourceLang,
        public LanguageCode $targetLang,
        public ?string $description = null,
        public ?string $topic = null,
    ) {}
}
