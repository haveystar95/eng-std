<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One self-contained card in a study session: prompt on the user's language, the target answer
 * (for grading feedback), and the mode-specific extras the client needs to play it offline —
 * shuffled options for multiple_choice, shuffled chips for word_bank, nothing for typing.
 */
final readonly class SessionCardView
{
    /**
     * @param  list<string>|null  $options  multiple_choice: the answer plus distractors, shuffled
     * @param  list<string>|null  $chips    word_bank: the answer's tokens, shuffled
     * @param  list<array{sentence: string, error_span: string, correction: string}>|null  $optionFeedback
     *        pick_correct: for each WRONG option, which fragment is broken and what it should have
     *        been. The reason this mode is worth building over multiple_choice — a wrong pick can be
     *        explained instead of merely marked. Rides with the card so the explanation works offline.
     * @param  list<string>  $acceptedVariants  other answers that count as correct for THIS card's
     *        `answer`, so the client's instant check matches the server's. Empty on the
     *        sentence-graded modes: a variant of the term is not a variant of the sentence.
     */
    public function __construct(
        public string $termId,
        public string $exerciseMode,
        public ?string $type,
        public ?string $prompt,
        public string $answer,
        public ?string $transcription,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?array $options,
        public ?array $chips,
        public array $acceptedVariants = [],
        public ?array $optionFeedback = null,
    ) {}
}
