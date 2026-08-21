<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * Renderable term content for the mobile client — the card's front, back and example.
 *
 * `acceptedVariants` is not decoration: the client grades typed answers offline, and a client that
 * does not know the variants would show «Не то» for an answer the server then accepts. It is the one
 * piece of the answer key the device is allowed to hold, and it must ride along with the term.
 */
final readonly class TermContentView
{
    /**
     * @param  list<string>  $acceptedVariants  extra correct answers, beyond `text`
     * @param  list<array{sentence: string, error_type: string, error_span: string, correction: string}>  $exampleDistractors
     *         wrong versions of `example`; mirrored ahead of the trainer that will use them
     */
    public function __construct(
        public string $id,
        public string $lang,
        public string $text,
        public string $type,             // word | phrase | idiom | phrasal_verb
        public ?string $transcription,   // IPA
        public ?string $translation,     // primary translation (source language)
        public ?string $example,
        public ?string $exampleTranslation,
        /**
         * What the word MEANS, written in the language being learned — the `description_match`
         * card's whole question. Null on everything written before descriptions existed (the store
         * catalogue), and the trainer refuses those by content rather than pretending.
         */
        public ?string $description = null,
        public ?string $imageUrl = null,        // Pexels photo (null = none/placeholder)
        public ?string $imageAuthor = null,     // photographer credit (Pexels licence)
        public ?string $imageAuthorUrl = null,  // link to the photographer
        public array $acceptedVariants = [],
        public array $exampleDistractors = [],
    ) {}
}
