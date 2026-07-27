<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic (Claude) Messages API provider. Uses tool-use to force
 * structured JSON output.
 */
class ClaudeProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $generateModel,
        private readonly string $checkModel,
    ) {}

    public function generateWords(string $topic, array $levels, int $size): array
    {
        $tool = [
            'name' => 'return_words',
            'description' => 'Return the generated vocabulary list.',
            'input_schema' => AiSchema::wordsSchema(),
        ];

        $prompt = AiPrompts::generate($topic, $levels, $size);
        $data = $this->call($this->generateModel, $prompt, $tool, 4096);

        return $data['words'] ?? [];
    }

    public function checkAnswer(string $term, string $expected, string $userAnswer, string $mode): array
    {
        $tool = [
            'name' => 'return_grade',
            'description' => 'Return the grading result for the user answer.',
            'input_schema' => AiSchema::gradeSchema(),
        ];

        $prompt = AiPrompts::check($term, $expected, $userAnswer, $mode);

        return $this->call($this->checkModel, $prompt, $tool, 512);
    }

    private function call(string $model, string $prompt, array $tool, int $maxTokens): array
    {
        $response = $this->client()->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => $tool['name']],
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Claude API error: ' . $response->status() . ' ' . $response->body());
        }

        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                return $block['input'] ?? [];
            }
        }

        throw new RuntimeException('Claude API returned no tool_use block.');
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60);
    }
}
