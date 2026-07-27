<?php

namespace App\Services\Ai;

/** Prompt text shared by all providers. */
class AiPrompts
{
    /** @param string[] $levels */
    public static function generate(string $topic, array $levels, int $size): string
    {
        $levelText = implode(', ', $levels);

        return "You are a CEFR vocabulary and phrasebook expert helping a Russian speaker learn English.\n"
            . "The user's request may be a topic, or a real-life SITUATION / GOAL, and may be written in Russian "
            . "(for example: \"иду открывать счёт в банке\", \"собеседование на работу\", \"заказать еду в кафе\").\n"
            . "Request: \"{$topic}\".\n"
            . "Generate exactly {$size} of the MOST USEFUL English items for that request. "
            . "Make it a BALANCED MIX and LEAN TOWARDS phrases and full sentences (they are easier to learn in context):\n"
            . "  • roughly 60-70% common phrases and ready-to-use full sentences the person would actually say or hear "
            . "(e.g. \"I'd like to open a bank account\", \"Could you tell me the monthly fee?\", \"Here is my proof of address\");\n"
            . "  • the rest key single words/terms (e.g. \"withdrawal\", \"account holder\").\n"
            . "Order them roughly by how useful/likely they are in the situation.\n"
            . "STRICT LEVEL RULE: keep items within CEFR level(s) {$levelText}; do not include items below the "
            . "lowest requested level. Set each item's cefr_level to its true level (one of {$levelText}).\n"
            . "For each item provide: the English term/phrase, an accurate Russian translation, IPA transcription "
            . "WITHOUT slashes for SINGLE words only (use an empty string for multi-word phrases), and a short "
            . "natural English example sentence set in that situation. Avoid duplicates. Respond with JSON only.";
    }

    public static function check(string $term, string $expected, string $userAnswer, string $mode): string
    {
        $task = $mode === 'usage'
            ? "The user was asked to use the English word \"{$term}\" in a sentence. Judge whether their sentence uses it correctly and naturally."
            : "The user was asked to translate the English word \"{$term}\" into Russian (reference translation: \"{$expected}\"). Judge whether their translation is correct, accepting valid synonyms.";

        return "{$task}\nUser answer: \"{$userAnswer}\"\n"
            . "Be encouraging but honest. Give feedback in Russian. Set 'score' to an integer from 0 to 100 "
            . "reflecting how correct the answer is (100 = perfect, 0 = completely wrong). "
            . "Set 'corrected' to the right answer if the user was wrong, otherwise an empty string. "
            . "Respond with JSON only.";
    }
}
