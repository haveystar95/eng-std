<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\WordLookupResult;
use App\Modules\Generation\Domain\Exception\LookupRefused;
use App\Modules\Generation\Domain\Service\DescriptionSelfReference;
use App\Modules\Generation\Domain\Service\TermOccurrence;
use App\Modules\Shared\Domain\Service\LanguagePurity;

/**
 * The barrier between the lookup model and the cache — the same rule as {@see LanguageBarrier}, in
 * the shape a single synchronous call can have.
 *
 * The difference from its big sibling is the REPAIR: there isn't one. The collection barrier can
 * re-ask for a translation because a generation is a background job with a budget; a lookup is a
 * person watching a spinner, and a second and third call to fix the first would triple both the
 * wait and the cost of the cheapest feature in the app. So this screen has exactly two verdicts —
 * write it, or refuse it and tell the learner the word could not be looked up.
 *
 * One thing is DEGRADED rather than refused: an example that does not contain its own term is
 * dropped, and the card is served without it. A card with a translation and a description is
 * useful; a card refused over its third field is not.
 */
final readonly class LookupBarrier
{
    public function __construct(private LanguagePurity $purity = new LanguagePurity()) {}

    /**
     * @throws LookupRefused when the answer cannot be stored
     */
    public function screen(WordLookupResult $result, string $targetLang, string $nativeLang): WordLookupResult
    {
        // The language being learned. No repair exists for these: an English field with Cyrillic in
        // it is not a translation that came back wrong, it is the wrong word.
        foreach (['text' => $result->text, 'description' => $result->description] as $field => $value) {
            if (! $this->purity->isClean($targetLang, $value)) {
                throw LookupRefused::wrongLanguage($field, $targetLang, $value);
            }
        }

        // The learner's language.
        foreach (['translation' => $result->translation] as $field => $value) {
            if (! $this->purity->isClean($nativeLang, $value)) {
                throw LookupRefused::wrongLanguage($field, $nativeLang, $value);
            }
        }

        // The card's whole reason to exist: a description that names its own word asks nothing.
        if (DescriptionSelfReference::givesAway($result->description, $result->text)) {
            throw LookupRefused::descriptionGivesAway($result->text, $result->description);
        }

        $example = $result->example;
        $exampleTranslation = $result->exampleTranslation;

        // Degradations, in order: an example that is in the wrong language, or that does not
        // actually contain the term, is not an example of this word — drop it and keep the card.
        if ($example !== null && (! $this->purity->isClean($targetLang, $example)
            || ! TermOccurrence::inExample($example, $result->text))) {
            $example = null;
            $exampleTranslation = null;
        }
        // A translation of a dropped example is meaningless; a translation in the wrong language is
        // worse than none, because the client would print it under the sentence as if it were one.
        if ($example === null || ($exampleTranslation !== null && ! $this->purity->isClean($nativeLang, $exampleTranslation))) {
            $exampleTranslation = null;
        }

        return new WordLookupResult(
            text: $result->text,
            type: $result->type,
            translation: $result->translation,
            description: $result->description,
            example: $example,
            exampleTranslation: $exampleTranslation,
            cefr: $result->cefr,
            transcription: $result->transcription,
            // Carried through untouched: the barrier screens LANGUAGE and self-reference, and an
            // image query is neither the learner's language nor the target one — it is always
            // English by contract, so screening it here would reject every correct answer.
            imageApiPrompt: $result->imageApiPrompt,
            // DEGRADED, never fatal — the same rule the example follows one field up. A synonym in
            // the wrong language, or one that is really the word itself, is dropped; the card is
            // still a card without it, and refusing the whole lookup over a third-tier field would
            // cost the learner the word they came for.
            synonyms: $this->clean($result->synonyms, $targetLang, [$result->text]),
            // Other readings are the LEARNER's language, screened against that side and never
            // allowed to repeat the answer the card asks.
            otherTranslations: $this->clean($result->otherTranslations, $nativeLang, [$result->translation]),
            model: $result->model,
            promptVersion: $result->promptVersion,
            tokensIn: $result->tokensIn,
            tokensOut: $result->tokensOut,
            costUsd: $result->costUsd,
        );
    }

    /**
     * Keep the entries that are in the language they claim and are not a restatement of `$reject`.
     *
     * Case- and whitespace-insensitive on the comparison only: what is stored is what the model
     * wrote. Deliberately no cross-list check between synonyms and other translations — they are in
     * different languages, so a collision between them is not a thing that can happen honestly.
     *
     * @param  list<string>  $values
     * @param  list<string>  $reject
     * @return list<string>
     */
    private function clean(array $values, string $lang, array $reject): array
    {
        $blocked = [];
        foreach ($reject as $value) {
            $blocked[$this->key($value)] = true;
        }

        $out = [];
        foreach ($values as $value) {
            $key = $this->key($value);
            if ($key === '' || isset($blocked[$key]) || ! $this->purity->isClean($lang, $value)) {
                continue;
            }
            $blocked[$key] = true;
            $out[] = trim($value);
        }

        return $out;
    }

    private function key(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($value)));
    }
}
