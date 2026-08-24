<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * The set of correct TARGET-language forms for a term — the answer key the server grades a
 * typed/assembled answer against. Always a set (one entry today, term text; alternative forms
 * like colour/color are added later without changing this shape). Translations are the prompt
 * side and never belong here.
 *
 * `example` is the term's PINNED example sentence (the same one the card showed — ordered by id,
 * like every other reader). It is carried here for the sentence-level exercises, which ask the
 * learner to reproduce that sentence rather than the term: the mode decides which of the two is
 * the expected answer ({@see \App\Modules\Learning\Domain\ValueObject\ExerciseMode::gradesAgainstExample()}).
 * Note what did NOT change: the key still holds only target-language text the term itself owns —
 * a translation is still never an accepted answer.
 */
final readonly class TermAnswerKeyView
{
    /**
     * @param  list<string>  $accepted  non-empty set of accepted target forms
     * @param  string|null   $example   the pinned example sentence, when the term has one
     * @param  list<string>  $synonyms  near-synonyms of the term, on the SAME (target) side. Kept
     *         out of `$accepted` deliberately: a synonym is not a form of this word, so it answers
     *         only a card that asked what the word MEANS — the grader adds it where the mode says
     *         it counts ({@see \App\Modules\Learning\Domain\ValueObject\ExerciseMode::acceptsSynonyms()})
     *         and nowhere else. Folding it into `$accepted` here would make a listening card accept
     *         a word the learner never heard, and there would be nothing left to tell the two apart.
     *         The answer-key rule is untouched: this is still only target-language text the term
     *         itself owns, never a translation.
     */
    public function __construct(
        public string $termId,
        public array $accepted,
        public bool $isPhrase,
        public ?string $example = null,
        public array $synonyms = [],
    ) {}
}
