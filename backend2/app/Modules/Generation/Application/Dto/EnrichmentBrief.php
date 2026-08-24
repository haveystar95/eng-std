<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What the model is given for ONE term. All of it is passed as delimited DATA, never as
 * instructions — the term and its example come from generated content, so they are exactly the
 * place a prompt injection would ride in.
 *
 * `acceptedForms` is included so the model doesn't spend output re-proposing answers the key
 * already has, and so it can see what "already correct" means before inventing a wrong sentence.
 */
final readonly class EnrichmentBrief
{
    /**
     * @param  list<string>  $existingSynonyms  near-synonyms the term already has — shown for the
     *        same reason `acceptedForms` is, so the model does not buy tokens re-proposing them
     * @param  list<string>  $acceptedForms
     * @param  string  $termLang  the language being learned
     * @param  string  $translationLang  the learner's language — it decides which native-speaker
     *        interference the distractors should imitate, so the prompt is written around it rather
     *        than around any one hardcoded language
     */
    public function __construct(
        public string $termId,
        public string $text,
        public array $acceptedForms,
        public ?string $translation,
        public ?string $exampleSentence,
        public ?string $exampleTranslation,
        public string $termLang = 'en',
        public string $translationLang = 'ru',
        public array $existingSynonyms = [],
    ) {}
}
