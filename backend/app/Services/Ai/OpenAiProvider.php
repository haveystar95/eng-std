<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI provider. Uses Structured Outputs (response_format: json_schema,
 * strict) so the model returns JSON that exactly matches our schema.
 */
class OpenAiProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $generateModel,
        private readonly string $checkModel,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function generateWords(string $topic, array $levels, int $size): array
    {
        $data = $this->chat(
            $this->generateModel,
            AiPrompts::generate($topic, $levels, $size),
            'vocabulary',
            AiSchema::wordsSchema(),
        );

        return $data['words'] ?? [];
    }

    public function checkAnswer(string $term, string $expected, string $userAnswer, string $mode): array
    {
        $data = $this->chat(
            $this->checkModel,
            AiPrompts::check($term, $expected, $userAnswer, $mode),
            'grade',
            AiSchema::gradeSchema(),
        );

        return [
            'correct' => (bool) ($data['correct'] ?? false),
            'score' => (int) ($data['score'] ?? 0),
            'feedback' => (string) ($data['feedback'] ?? ''),
            'corrected' => ($data['corrected'] ?? '') ?: null,
        ];
    }

    private function chat(string $model, string $prompt, string $schemaName, array $schema): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(90)
            ->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $schemaName,
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: ' . $response->status() . ' ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('OpenAI returned empty content.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned non-JSON content: ' . $content);
        }

        return $decoded;
    }
}
