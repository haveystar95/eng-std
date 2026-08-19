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
        public string $type,       // 'word' | 'phrase' | 'idiom' | 'phrasal_verb'
        public ?string $pos,       // PartOfSpeech value, or null
        public string $source,     // 'curated' | 'ai' | 'user'
        public array $translations = [],
        public ?string $ipa = null,
        public array $examples = [],
        public ?string $cefr = null,   // 'A1'..'C2' or null; validated/normalized downstream
        public ?string $imageApiPrompt = null,   // model's image-search query, stored for AttachImagesJob
        // WHO wrote this item: the versioned prompt file and the model that answered. Primitives,
        // like everything else on this shim — Generation must not touch Vocabulary's Domain VOs.
        // Both null (the default) means "not generated / origin not recorded", which is what a
        // caller that genuinely does not know must pass; it is never filled in with a guess.
        public ?string $promptVersion = null,
        public ?string $generationModel = null,
    ) {}
}
