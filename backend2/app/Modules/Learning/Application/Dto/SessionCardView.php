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
     * @param  list<string>  $synonyms  near-synonyms of the term that count as correct on THIS card,
     *        beside `acceptedVariants` and never inside it. Present only where the card's prompt is
     *        the MEANING — the server accepts a synonym on exactly those cards
     *        ({@see \App\Modules\Learning\Domain\ValueObject\ExerciseMode::acceptsSynonyms()}),
     *        so a client that merged the two lists would accept `goal` on a listening card for
     *        `purpose`, i.e. be looser than the server. Empty everywhere else, including the
     *        sentence-graded modes.
     * @param  int|null  $ladderStep  which rung of the acquisition ladder this card was dealt at.
     *        The client echoes it back with the answer, because the pair's rung MOVES the moment
     *        that answer is folded — without it the server could not tell afterwards what the card
     *        had asked. Null for a `known` verification, which is outside the ladder.
     * @param  list<string>|null  $optionIds  present ONLY on the forward-recognition card (rung 1),
     *        aligned index-for-index with `options`: the term each option's translation belongs to.
     *        That card is graded by IDENTITY — the learner taps, the client uploads the tapped id,
     *        and `answer` below is this card's own term id. It is the one card whose correct option
     *        is a translation, and it is exactly why no translation ever enters a text answer key.
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
        public array $synonyms = [],
        public ?array $optionFeedback = null,
        public ?int $ladderStep = null,
        public ?array $optionIds = null,
    ) {}
}
