<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One word, as the model returned it — already shaped, not yet screened and not yet stored.
 *
 * The DESCRIPTION is the field this whole product exists for and the one with the strictest rule:
 * it is written in the language being LEARNED (the trainer shows it as the question), it is one or
 * two simple sentences at A2–B1, and it must not contain the word it describes — a definition that
 * uses its own headword answers the card for free.
 */
final readonly class WordLookupResult
{
    public function __construct(
        public string $text,
        public string $type,           // word | phrase
        public string $translation,
        public string $description,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?string $cefr,          // A1..C2, or null when the model would not commit
        public ?string $transcription, // IPA
        /**
         * The model's own image-search query for this word — English, concrete, and EMPTY when the
         * word is genuinely un-illustratable. Null/empty means «no photo», never «guess one»: the
         * pending-image reader treats a blank query as a deliberate refusal.
         */
        public ?string $imageApiPrompt,
        /**
         * How the word READS, written in the letters of the learner's own language — «джоб
         * интервью». Shown beside the card the moment the learner searches, so they can say the word
         * before they can spell it.
         *
         * SHOWN, never stored. `term_transliterations` keeps one canonical reading per (term, lang)
         * and its two writers are the core and the single-card reading job, both on the strong model
         * — that is where the 49/49 measurement was taken. This one is bought from the cheap search
         * model as part of an answer that was being paid for anyway; letting it write the canonical
         * row would silently move the product onto a different producer.
         *
         * Null on every version below v6 — an absence, not a claim that the word reads as spelled.
         */
        public ?string $transliteration,
        /**
         * Near-SYNONYMS of the word, in the language being LEARNED — `purpose` → `goal`, `aim`.
         * Zero to three, and zero is the ordinary answer for anything longer than a short lemma.
         *
         * @var list<string>
         */
        public array $synonyms,
        /**
         * ADDITIONAL readings of the word in the learner's language, beside `translation`, when the
         * word is genuinely polysemous — `bank` → «банк», and also «берег».
         *
         * At most two, and `translation` is not among them: that one is the answer the card asks and
         * this is what else the word can mean. Kept as data only — there is no «other meanings» UI
         * and this наряд does not add one; the point is that a learner who types «берег» for `bank`
         * is not told they are wrong.
         *
         * @var list<string>
         */
        public array $otherTranslations,
        public string $model,
        public string $promptVersion,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
        public ?string $costUsd = null,
        /**
         * The model could not name a word for this query at all — keystrokes, a fragment, nothing
         * it could place in either language.
         *
         * An honest FIELD and not an exception, for the same reason the daily cap is one: «не
         * получилось распознать, проверьте написание» is a normal answer the app has a line for,
         * and modelling it as a failure would send it down the error path, where it would read as
         * «the app is broken» instead of «check the spelling». Every other field is meaningless
         * when this is true and none of them is screened.
         */
        public bool $notRecognized = false,
    ) {}

    /** @return array<string, mixed> the cacheable half — everything except what the call cost */
    public function toPayload(): array
    {
        // «This is not a word» is a fact about the query and just as permanent as a card, so it is
        // cached like one: the next person to paste the same keystrokes pays nothing, and the daily
        // cap — which counts rows — still sees that a call was bought.
        if ($this->notRecognized) {
            return ['not_recognized' => true];
        }

        return [
            'text' => $this->text,
            'type' => $this->type,
            'translation' => $this->translation,
            'description' => $this->description,
            'example' => $this->example,
            'example_translation' => $this->exampleTranslation,
            'cefr' => $this->cefr,
            'transcription' => $this->transcription,
            'image_api_prompt' => $this->imageApiPrompt,
            // NOT cached, deliberately, and this is the line to change if the reading is ever
            // switched on: the cache is keyed by (query, pair) and never by prompt version, so the
            // only thing that brings an old row back to the model is a KEY it does not carry being
            // added to the staleness check in LookupWordHandler. Writing the key now — always null,
            // because no shipped version asks — would spend that mechanism on nothing and leave the
            // real switch-on with no way to re-buy the 45 rows already in the table.
            'synonyms' => $this->synonyms,
            'other_translations' => $this->otherTranslations,
        ];
    }
}
