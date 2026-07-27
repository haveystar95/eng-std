<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Local Ollama provider. Uses Ollama's structured-output `format` param
 * (a JSON schema) to get reliable JSON back from the model.
 */
class OllamaProvider implements AiProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
    ) {}

    public function generateWords(string $topic, array $levels, int $size): array
    {
        $data = $this->chat(
            AiPrompts::generate($topic, $levels, $size),
            AiSchema::wordsSchema(),
        );

        return $data['words'] ?? [];
    }

    public function checkAnswer(string $term, string $expected, string $userAnswer, string $mode): array
    {
        $data = $this->chat(
            AiPrompts::check($term, $expected, $userAnswer, $mode),
            AiSchema::gradeSchema(),
        );

        return [
            'correct' => (bool) ($data['correct'] ?? false),
            'score' => (int) ($data['score'] ?? 0),
            'feedback' => (string) ($data['feedback'] ?? ''),
            'corrected' => ($data['corrected'] ?? '') ?: null,
        ];
    }

    private function chat(string $prompt, array $schema): array
    {
        $response = Http::timeout(240)->post(rtrim($this->baseUrl, '/') . '/api/chat', [
            'model' => $this->model,
            'stream' => false,
            'format' => $schema,
            'options' => ['temperature' => 0.4],
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Ollama error: ' . $response->status() . ' ' . $response->body());
        }

        $content = $response->json('message.content');
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Ollama returned empty content.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Ollama returned non-JSON content: ' . $content);
        }

        return $decoded;
    }
}
