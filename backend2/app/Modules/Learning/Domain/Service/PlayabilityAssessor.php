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
     */
    public function assess(string $answer, ?string $example, ?string $exampleTranslation = null): TermPlayability
    {
        $hasExample = $example !== null && $example !== '';

        return new TermPlayability(
            answerWordCount: $this->chips->wordCount($answer),
            // A blank can be cut only from an example that actually holds the answer.
            // Case-insensitive, matching the client's blanking.
            clozeable: $hasExample && mb_stripos((string) $example, $answer) !== false,
            exampleTokenCount: $hasExample ? $this->tokenizer->count((string) $example) : 0,
            hasExampleTranslation: $exampleTranslation !== null && trim($exampleTranslation) !== '',
            // A "term" that is itself the whole sentence — scrambling it would just be word_bank.
            exampleIsAnswer: $hasExample && $this->tokenizer->sameTokens((string) $example, $answer),
        );
    }
}
