<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The three programme tabs of the window — Слова · Фразы · Диалог — each with its summary. */
final readonly class WindowProgramView
{
    /**
     * @param  list<WindowWordView>  $words
     * @param  list<WindowPhraseView>  $phrases
     * @param  list<WindowPairView>  $dialogue
     */
    public function __construct(
        public array $words,
        public WindowSummaryView $wordsSummary,
        public array $phrases,
        public WindowSummaryView $phrasesSummary,
        public array $dialogue,
        public WindowSummaryView $dialogueSummary,
    ) {}
}
