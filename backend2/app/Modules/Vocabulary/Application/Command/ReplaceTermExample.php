<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Replace a term's usage example with a new one (the "New example" action). Examples are persisted
 * add-only and aren't hydrated into the aggregate, so replacement is a dedicated write on the
 * term's own examples rather than an aggregate mutation.
 */
final readonly class ReplaceTermExample
{
    /**
     * @param  list<string>  $dropDistractorSentences  distractors of the pinned example that the NEW
     *         sentence orphans — see {@see \App\Modules\Vocabulary\Application\Port\TermExampleWriter}.
     *         Which ones those are is the caller's judgement: a distractor is a one-place break of one
     *         specific sentence, and only the module that owns that rule can apply it. Empty keeps
     *         every distractor, which is what a caller that has not asked the question must pass.
     * @param  string  $source  who wrote the sentence: `ai` when a model did, `user` when a person
     *         typed it. Primitives all the way, like {@see ImportTerm} — a caller in another module
     *         must not have to touch Vocabulary's Domain value objects to say where content came from.
     * @param  string|null  $promptVersion  the versioned prompt file behind the sentence
     * @param  string|null  $generationModel  the model that answered
     */
    public function __construct(
        public TermId $termId,
        public string $sentence,
        public ?string $sentenceTranslation,
        public array $dropDistractorSentences = [],
        public string $source = 'user',
        public ?string $promptVersion = null,
        public ?string $generationModel = null,
    ) {}
}
