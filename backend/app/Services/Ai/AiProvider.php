<?php

namespace App\Services\Ai;

/**
 * Abstraction over the LLM backend (Claude, Ollama, …) so the rest of the app
 * doesn't care which one is configured. Swap via `AI_PROVIDER` in .env.
 */
interface AiProvider
{
    /**
     * @param string[] $levels One or more CEFR levels to span (e.g. ['A2','B1']).
     * @return array<int, array{term:string,translation:string,transcription:?string,example:?string,cefr_level:?string}>
     */
    public function generateWords(string $topic, array $levels, int $size): array;

    /**
     * @return array{correct:bool,score:int,feedback:string,corrected:?string}
     */
    public function checkAnswer(string $term, string $expected, string $userAnswer, string $mode): array;
}
