<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * WHAT THE MODEL HAS TO KNOW ABOUT THESE TWO LANGUAGES — the `{{target_lang_notes}}` and
 * `{{support_lang_notes}}` of P1 and P2 v0.4.
 *
 * Both prompts open with the pair and then a line about each side, and the placeholders exist
 * because the notes are FACTS about a language rather than instructions about a plan: Romanian's
 * comma-below letters are misspelled by every model that has not been reminded, and a Cyrillic
 * support language means every card needs a reading aid or the learner cannot say it at all.
 *
 * Two rules and a table:
 *
 *   the SCRIPT rule is computed — different alphabets ⇒ the reading hint is mandatory, and that is
 *   the same question {@see PlanDayValidator::scriptsDiffer()} answers, so the prompt and the gate
 *   cannot disagree about whether a missing hint was asked for;
 *   the ORTHOGRAPHY notes are a table, one line per language, and a language absent from it gets
 *   NO line rather than a made-up one — a note nobody wrote is not a note about nothing.
 *
 * Pure and in Domain: it is a sentence about a language, and the two prompts must render the same
 * one.
 */
final class PlanLanguageNotes
{
    /**
     * Per-language orthography reminders, in the prompt's own voice.
     *
     * @var array<string, string>
     */
    private const TARGET_NOTES = [
        'en' => 'English substitutes bare: a word goes into a frame unchanged, so a filler is the '
            . 'card\'s own text character for character.',
        'ro' => 'Romanian uses the comma-below letters ș and ț (U+0219, U+021B), never the cedilla '
            . 'forms ş/ţ; ă, â and î are written wherever the word has them.',
        'de' => 'German capitalises every noun, and ä/ö/ü/ß are written out rather than transcribed.',
    ];

    /** @var array<string, string> */
    private const SUPPORT_NOTES = [
        'ru' => 'Russian, and Russian only: never Ukrainian words, forms or letters — «нужно», not '
            . '«треба». The letters і, ї, є, ґ never appear.',
        'uk' => 'Ukrainian, and Ukrainian only: never Russian forms in place of Ukrainian ones.',
    ];

    /** The line the target language gets, or '' when nothing is written about it. */
    public function target(string $targetLang): string
    {
        $note = self::TARGET_NOTES[$this->key($targetLang)] ?? '';

        return $note === '' ? '' : '- Target-language notes: ' . $note;
    }

    /**
     * The line the support language gets — its own note, plus the reading-aid rule when the two
     * alphabets differ, because that is the one note that is about the PAIR.
     */
    public function support(string $supportLang, string $targetLang, bool $scriptsDiffer): string
    {
        $parts = [];
        $note = self::SUPPORT_NOTES[$this->key($supportLang)] ?? '';
        if ($note !== '') {
            $parts[] = $note;
        }
        if ($scriptsDiffer) {
            $parts[] = 'The two languages use different alphabets, so a reading aid is REQUIRED: '
                . 'every card carries "transliteration" — how the card sounds, in the letters of the '
                . 'support language only, with no punctuation and no letters of the target alphabet.';
        }

        return $parts === [] ? '' : '- Support-language notes: ' . implode(' ', $parts);
    }

    private function key(string $lang): string
    {
        return mb_strtolower(mb_substr(trim($lang), 0, 2));
    }
}
