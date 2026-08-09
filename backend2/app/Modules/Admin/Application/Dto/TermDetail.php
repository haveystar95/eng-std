<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A term with its translations, examples, the collections holding it, and its progress footprint. */
final readonly class TermDetail
{
    /**
     * @param list<TermTranslationRow> $translations
     * @param list<TermExampleRow> $examples
     * @param list<CollectionRefRow> $collections
     */
    public function __construct(
        public string $id,
        public string $lang,
        public string $text,
        public string $normalizedText,
        public string $type,
        public ?string $pos,
        public ?string $ipa,
        public ?string $audioUrl,
        public string $source,
        public ?string $createdAt,
        public array $translations,
        public array $examples,
        public array $collections,
        public int $progressCount,   // how many (user,term) progress rows reference this term
    ) {}
}
