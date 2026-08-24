<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class CreateCustomCollection
{
    /**
     * @param  ?LanguageCode  $sourceLang  the language of SUPPORT; null = the owner's profile default
     * @param  ?LanguageCode  $targetLang  the language BEING LEARNED; null = the owner's profile default
     * @param  ?CollectionId  $id  client-supplied id for offline idempotency (else generated)
     */
    public function __construct(
        public UserId $ownerId,
        public string $title,
        public ?LanguageCode $sourceLang = null,
        public ?LanguageCode $targetLang = null,
        public ?string $description = null,
        public ?string $topic = null,
        public ?CollectionId $id = null,
    ) {}
}
