<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Prompt;

/**
 * Renders the versioned practice-dialog prompt template against a lesson into the instructions the
 * realtime model is briefed with. Kept separate from the minting adapter so the substitution is
 * unit-testable without any network call.
 */
final class PracticeDialogInstructions
{
    private const LANGUAGE_NAMES = [
        'en' => 'English', 'ru' => 'Russian', 'uk' => 'Ukrainian', 'es' => 'Spanish',
        'de' => 'German', 'fr' => 'French', 'it' => 'Italian', 'pt' => 'Portuguese',
        'pl' => 'Polish', 'tr' => 'Turkish', 'zh' => 'Chinese', 'ja' => 'Japanese',
    ];

    /** @param array<string, mixed> $lesson */
    public function render(string $template, array $lesson): string
    {
        $texts = $this->targetWordTexts($lesson);
        // Multi-word items are phrases/questions the AGENT says; single words are what the LEARNER
        // must produce. Same split the coverage check uses, so the brief and the scoring agree.
        $phrases = array_values(array_filter($texts, fn (string $t): bool => str_contains(trim($t), ' ')));
        $words = array_values(array_filter($texts, fn (string $t): bool => ! str_contains(trim($t), ' ')));

        return strtr($template, [
            '{{topic}}' => $this->str($lesson, 'topic'),
            '{{level}}' => $this->str($lesson, 'level'),
            '{{target_language}}' => $this->languageName($this->str($lesson, 'target')),
            '{{native_language}}' => $this->languageName($this->str($lesson, 'native')),
            '{{target_words}}' => $this->bullets($texts),   // v1 kept the words in one list
            '{{agent_phrases}}' => $this->bullets($phrases),
            '{{elicit_words}}' => $this->bullets($words),
        ]);
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return list<string>
     */
    private function targetWordTexts(array $lesson): array
    {
        $words = is_array($lesson['target_words'] ?? null) ? $lesson['target_words'] : [];
        $texts = [];
        foreach ($words as $word) {
            $text = is_array($word) && is_string($word['text'] ?? null) ? $word['text'] : null;
            if ($text !== null && $text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /** @param list<string> $texts */
    private function bullets(array $texts): string
    {
        return $texts === [] ? '- (none)' : implode("\n", array_map(static fn (string $t): string => '- ' . $t, $texts));
    }

    private function languageName(string $code): string
    {
        return self::LANGUAGE_NAMES[$code] ?? $code;
    }

    /** @param array<string, mixed> $lesson */
    private function str(array $lesson, string $key): string
    {
        return is_string($lesson[$key] ?? null) ? $lesson[$key] : '';
    }
}
