<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;

/**
 * Cross-module entry point for creating a term from primitives, so callers (e.g. Generation)
 * never touch Vocabulary's Domain value objects. Maps to FindOrCreateTerm internally, so
 * global deduplication still applies.
 */
final readonly class ImportTerm
{
    /**
     * @param list<TranslationInput> $translations
     * @param list<ExampleInput> $examples
     */
    public function __construct(
        public LanguageCode $lang,
        public string $text,
        public string $type,       // 'word' | 'phrase'
        public ?string $pos,       // PartOfSpeech value, or null
        public string $source,     // 'curated' | 'ai' | 'user'
        public array $translations = [],
        public ?string $ipa = null,
        public array $examples = [],
        public ?string $cefr = null,   // 'A1'..'C2' or null; validated/normalized downstream
    ) {}
}
