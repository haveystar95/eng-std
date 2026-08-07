<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\DialogSummaryBrief;
use App\Modules\Generation\Application\Dto\DialogSummaryResult;
use App\Modules\Generation\Application\Port\DialogSummarizerPort;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One cheap text call that recaps a finished practice dialog in the learner's native language: what
 * went well and one or two main mistakes. Not the model's role-play brief (that's the versioned
 * realtime prompt) — just a short after-the-fact summary, so the instruction is inline and terse.
 */
final class OpenAiDialogSummarizer implements DialogSummarizerPort
{
    private const LANGUAGE_NAMES = [
        'en' => 'English', 'ru' => 'Russian', 'uk' => 'Ukrainian', 'es' => 'Spanish',
        'de' => 'German', 'fr' => 'French', 'it' => 'Italian', 'pt' => 'Portuguese',
        'pl' => 'Polish', 'tr' => 'Turkish', 'zh' => 'Chinese', 'ja' => 'Japanese',
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function summarize(DialogSummaryBrief $brief): DialogSummaryResult
    {
        $native = self::LANGUAGE_NAMES[$brief->nativeLang] ?? $brief->nativeLang;
        $system = "You review a short language-practice conversation. Write a 1–2 sentence recap in "
            . "{$native}: what the learner did well, and the one or two most important mistakes to fix. "
            . 'Be encouraging and specific. Output only the recap, no preamble.';

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => "TOPIC: {$brief->topic}\n\nTRANSCRIPT:\n" . $this->transcript($brief->lines)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI summary error: ' . $response->status() . ' ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned an empty summary.');
        }

        return new DialogSummaryResult(
            summary: trim($content),
            tokensIn: is_int($response->json('usage.prompt_tokens')) ? $response->json('usage.prompt_tokens') : null,
            tokensOut: is_int($response->json('usage.completion_tokens')) ? $response->json('usage.completion_tokens') : null,
            model: $this->model,
        );
    }

    /** @param list<TranscriptLine> $lines */
    private function transcript(array $lines): string
    {
        $out = [];
        foreach ($lines as $line) {
            $who = $line->role->isUser() ? 'Learner' : 'Tutor';
            $out[] = "{$who}: {$line->text}";
        }

        return $out === [] ? '(no speech was transcribed)' : implode("\n", $out);
    }
}
