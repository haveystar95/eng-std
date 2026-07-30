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
    ) {}
}
