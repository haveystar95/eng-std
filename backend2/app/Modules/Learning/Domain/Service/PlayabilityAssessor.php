<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\TermPlayability;

/**
 * Derives a term's {@see TermPlayability} from its raw content. Lives here, not in the two
 * Application call sites that need it (the live session assembler and the admin day-plan
 * simulator), because a plan that disagrees with the session it simulates is worse than no plan.
 *
 * Takes primitives on purpose: Learning must not import Vocabulary's DTOs.
 */
final readonly class PlayabilityAssessor
{
    public function __construct(
        private ChipShuffler $chips,
        private SentenceTokenizer $tokenizer,
    ) {}

    /**
     * @param  string       $answer              the target answer (the term's own text)
     * @param  string|null  $example             the term's pinned example sentence, if it has one
     * @param  string|null  $exampleTranslation  that sentence in the user's language
     * @param  int          $distractorCount     validated wrong versions of that sentence
     * @param  string|null  $description         what the word MEANS, in the language being learned
     */
    public function assess(
        string $answer,
        ?string $example,
        ?string $exampleTranslation = null,
        int $distractorCount = 0,
        ?string $description = null,
    ): TermPlayability {
        $hasExample = $example !== null && $example !== '';

        $answerWordCount = $this->chips->wordCount($answer);

        return new TermPlayability(
            answerWordCount: $answerWordCount,
            // A blank can be cut only from an example that actually holds the answer.
            // Case-insensitive, matching the client's blanking.
            clozeable: $hasExample && mb_stripos((string) $example, $answer) !== false,
            exampleTokenCount: $hasExample ? $this->tokenizer->count((string) $example) : 0,
            hasExampleTranslation: $exampleTranslation !== null && trim($exampleTranslation) !== '',
            // A "term" that is itself the whole sentence — scrambling it would just be word_bank.
            exampleIsAnswer: $hasExample && $this->tokenizer->sameTokens((string) $example, $answer),
            // Distractors belong to the PINNED example; with no example there is nothing they could
            // hang off, so they cannot make the term playable on their own.
            distractorCount: $hasExample ? $distractorCount : 0,
            // Unlike the distractors, this hangs off nothing: a description is about the WORD, so a
            // term with no example still has one and description_match still plays.
            hasDescription: $description !== null && trim($description) !== '',
            // Only for a SINGLE word: that is the answer word_bank deals as letters. A phrase is
            // assembled from its words, so counting its characters would say nothing about its card.
            answerCharCount: $answerWordCount === 1 ? mb_strlen(trim($answer)) : 0,
        );
    }
}
