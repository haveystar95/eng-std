<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Ask for a collection to be generated from a free-text prompt (a topic or a situation). */
final readonly class RequestCollectionGeneration
{
    /**
     * @param  list<string>  $levels
     * @param  LanguageCode|null  $targetLang  null → fall back to the user's default learning
     *         language (profiles.target_language), then to English.
     */
    public function __construct(
        public UserId $userId,
        public string $prompt,
        public LanguageCode $sourceLang,
        public ?LanguageCode $targetLang,
        public array $levels,
        public int $size,
    ) {}
}
