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
     * @param  list<string>  $synonyms  near-synonyms on the STUDIED side. Beside `acceptedVariants`
     *         and not inside it, because the two are accepted on different cards: a variant is
     *         another spelling of this word and counts wherever the word is typed, a synonym is
     *         another word and only answers a card that asked for the MEANING
     *         ({@see \App\Modules\Learning\Domain\ValueObject\ExerciseMode::acceptsSynonyms()}).
     * @param  string|null  $transliterationHint  how the TERM reads, spelled in the letters of the
     *         asking (support) language — «cómo estás» → «комо эстас». Named apart from
     *         `$transcription`, which is IPA and a different product: IPA is one per term in a
     *         notation the learner has to have been taught, this is per PAIR in an alphabet they
     *         already read. Neither replaces the other.
     * @param  list<string>  $translations  every translation this term has in the asking language,
     *         primary first — the one on `$translation` plus its alternatives. Additive: readers
     *         that only want the question keep reading `$translation`.
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
        public array $synonyms = [],
        public array $translations = [],
        public ?string $transliterationHint = null,
        /**
         * What this expression DOES in the plan day that wrote it: `line`, `word` or `chunk`.
         *
         * NULL on every term that never came from a plan — which is most of the store, and is not
         * the same as «word». The plan's stage ladder reads it to pick a checklist
         * ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}); everything else
         * ignores it. Read from the term rather than guessed from `type`, which was the old proxy
         * and starts lying the moment a connector («deal with», `type: phrasal_verb`) appears.
         */
        public ?string $kind = null,
        /**
         * The day's line with its slot as `___` — «I worked on ___» — or null on a formula and on
         * everything that is not from a plan day. What a gap is cut from, in the plan session.
         */
        public ?string $frame = null,
        /**
         * WHOSE TURN this line is: `learner` or `role`. Null on everything that is not a plan line.
         *
         * A `role` line is what the INTERLOCUTOR says — «Hello. What seems to be the problem with
         * your child?». It is in the day so the learner will understand it when it is said to them,
         * and it is the one card of a plan they are never asked to produce. The live run dealt it
         * as an ordinary card to learn and showed it in «Фразы дня» with nothing marking whose line
         * it is (Д-8), so the learner spent a word bank and a speaking card rehearsing the doctor's
         * question.
         */
        public ?string $speaker = null,
        /**
         * WHAT A SPOKEN CARD FOR THIS LINE ASKS FOR — the piece, not the sentence.
         *
         * Chosen when the day is written ({@see \App\Modules\Generation\Domain\Service\PlanSpeakingKey}):
         * the frame's filler, or a word of the same day standing inside a formula, or nothing.
         * NULL means «the whole line», which is a real answer and is what the card then says out
         * loud. Null on every term that is not a plan line.
         */
        public ?string $speakingKey = null,
    ) {}
}
