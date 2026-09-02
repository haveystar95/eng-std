<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * ONE OF THE FIVE PHRASES EVERY PLAN CARRIES — «Помедленнее, пожалуйста», «Повторите ещё раз».
 *
 * Канон §5. They are the difference between a conversation that survives a misheard sentence and
 * one that stops there, and they are the same five in every plan of a language — which is exactly
 * why they are CONFIG and not generation: a phrase the whole product depends on must not be
 * re-invented per plan by a model that has already spelled it four different ways.
 *
 * The server writes them into day 1 like any other card and deals them in every warm-up
 * ({@see PlanShelf::Rescue}); P2 sees them only as a forbidden list, and a day that teaches one
 * again is a clone ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::CLONE}).
 */
final readonly class RescuePhrase
{
    public function __construct(
        /** The phrase in the language being learned — what the learner will say out loud. */
        public string $text,
        /** Its key in the learner's own language. */
        public string $translation,
        /** How it reads in the support alphabet, when the two scripts differ. */
        public ?string $transliteration = null,
        /** One sentence putting it in a real moment, so the card is not a bare formula. */
        public string $example = '',
        public string $exampleTranslation = '',
        /** The picture query. A rescue phrase is a moment, and a moment is photographable. */
        public string $imageApiPrompt = '',
    ) {}

    /**
     * @param  array<string, mixed>  $row  one entry of the language pack
     */
    public static function fromArray(array $row): ?self
    {
        $text = is_string($row['text'] ?? null) ? trim($row['text']) : '';
        $translation = is_string($row['translation'] ?? null) ? trim($row['translation']) : '';
        if ($text === '' || $translation === '') {
            // Half a phrase is not a rescue phrase: a card with no key cannot be graded, and a kit
            // that silently shrank is worse than one that says it is empty.
            return null;
        }

        $hint = is_string($row['transliteration'] ?? null) ? trim($row['transliteration']) : '';

        return new self(
            text: $text,
            translation: $translation,
            transliteration: $hint === '' ? null : $hint,
            example: is_string($row['example'] ?? null) ? trim($row['example']) : '',
            exampleTranslation: is_string($row['example_translation'] ?? null) ? trim($row['example_translation']) : '',
            imageApiPrompt: is_string($row['image_api_prompt'] ?? null) ? trim($row['image_api_prompt']) : '',
        );
    }
}
