<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Ask for a collection to be generated from a free-text prompt (a topic or a situation). */
final readonly class RequestCollectionGeneration
{
    /**
     * @param  list<string>  $levels
     * @param  LanguageCode|null  $targetLang  null → fall back to the user's default learning
     *         language (profiles.target_language), then to English.
     * @param  GenerationRequestId|null  $id  a client-generated ULID for offline idempotency —
     *         re-sending the same id returns the existing request instead of creating a new one.
     */
    public function __construct(
        public UserId $userId,
        public string $prompt,
        public LanguageCode $sourceLang,
        public ?LanguageCode $targetLang,
        public array $levels,
        public int $size,
        public ?GenerationRequestId $id = null,
    ) {}
}
