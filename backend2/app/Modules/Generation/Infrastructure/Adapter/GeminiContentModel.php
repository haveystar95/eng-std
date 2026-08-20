<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google's Gemini API (`v1beta/models/{model}:generateContent`). Four things differ from the other
 * adapters, and each is why this is its own class rather than another binding of one of them:
 *
 *  - **auth is a header, not a bearer token.** The documented default puts the key in the query
 *    string (`?key=…`); this uses `x-goog-api-key` instead — equally documented, and it keeps the
 *    secret out of a URL that the Observability listener records for every outbound call. The
 *    realtime minter beside it already authenticates this way, so there is one convention here.
 *  - **the system prompt is `systemInstruction`**, a field of its own, not the first turn of the
 *    conversation.
 *  - **`responseSchema` is not JSON Schema.** It is an OpenAPI-3.0-flavoured subset, and a request
 *    carrying `additionalProperties` is REJECTED rather than ignored — so the shared schema is
 *    translated on the way out (see {@see toGeminiSchema}). This is the one place where "all four
 *    providers get the same schema" is not literally true, and it cannot be: the same *constraint*
 *    is expressed in the dialect each vendor accepts.
 *  - **a safety block is a 200** with no candidate. Read as "malformed JSON" it sends a reader
 *    hunting for a parsing bug, so it is named for what it is.
 */
final readonly class GeminiContentModel implements ContentModelPort
{
    public function __construct(
        private OutboundCallContext $context,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        private ModelCost $cost = new ModelCost(),
        private int $timeoutSeconds = 180,
        private int $retries = 4,
    ) {}

    public function provider(): ProviderId
    {
        return ProviderId::Gemini;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
    {
        $startedAt = hrtime(true);

        $response = $this->context->run('generation', null, fn () => Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout($this->timeoutSeconds)
            // Same policy as the other adapters: escalating backoff, and only on statuses that can
            // change by themselves. Google adds 503 UNAVAILABLE under load to the usual 429.
            ->retry(
                $this->retries,
                static fn (int $attempt): int => $attempt * 4000,
                static fn (\Throwable $e): bool => ! $e instanceof RequestException
                    || in_array($e->response->status(), [408, 409, 429, 500, 502, 503, 504], true),
                throw: false,
            )
            ->post(rtrim($this->baseUrl, '/') . '/models/' . $this->model . ':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => $userMessage]]]],
                'systemInstruction' => ['parts' => [['text' => $prompt->text]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->toGeminiSchema($schema),
                ],
            ]));

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: ' . $response->status() . ' ' . mb_substr($response->body(), 0, 500));
        }

        // A refused prompt and a truncated answer both arrive as a 200. Named before parsing, so the
        // report says why nothing came back instead of blaming the JSON decoder.
        $blockReason = $response->json('promptFeedback.blockReason');
        if (is_string($blockReason) && $blockReason !== '') {
            throw new RuntimeException('Gemini blocked the prompt (blockReason=' . $blockReason . ').');
        }

        $finishReason = $response->json('candidates.0.finishReason');
        if (is_string($finishReason) && ! in_array($finishReason, ['STOP', 'MAX_TOKENS'], true)) {
            throw new RuntimeException('Gemini stopped early (finishReason=' . $finishReason . ').');
        }

        $content = $this->text($response->json('candidates.0.content.parts'));
        if ($content === null) {
            throw new RuntimeException('Gemini returned no text content (finishReason='
                . (is_string($finishReason) ? $finishReason : '?') . ').');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned malformed JSON: ' . mb_substr($content, 0, 500));
        }

        $tokensIn = is_int($response->json('usageMetadata.promptTokenCount')) ? $response->json('usageMetadata.promptTokenCount') : null;
        $tokensOut = is_int($response->json('usageMetadata.candidatesTokenCount')) ? $response->json('usageMetadata.candidatesTokenCount') : null;
        // `modelVersion` is what actually served the request — an alias like `gemini-3.7-flash` can
        // resolve to a dated build, and the ledger must price what ran.
        $model = is_string($response->json('modelVersion')) ? $response->json('modelVersion') : $this->model;

        /** @var array<string, mixed> $decoded */
        return new ModelAnswer(
            payload: $decoded,
            model: $model,
            latencyMs: $latencyMs,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $this->cost->estimate($model, $tokensIn, $tokensOut),
            raw: mb_substr($content, 0, 4000),
        );
    }

    /**
     * A long answer can come back split across several `parts`; concatenating them is what keeps a
     * 12-item collection from being parsed as a truncated fragment.
     */
    private function text(mixed $parts): ?string
    {
        if (! is_array($parts)) {
            return null;
        }

        $text = '';
        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return trim($text) !== '' ? $text : null;
    }

    /**
     * Translate our JSON Schema into the subset `responseSchema` accepts.
     *
     * Gemini's Schema is modelled on OpenAPI 3.0, not on JSON Schema, and the difference is not
     * cosmetic: `additionalProperties` — which the other two providers REQUIRE for strict mode —
     * makes Gemini reject the request outright. Dropping it does not loosen the contract in
     * practice, because `required` still lists every property and the answer is validated against
     * our own reading afterwards either way.
     *
     * `propertyOrdering` is added because Gemini documents field order as significant to output
     * quality and does not otherwise guarantee it; without it the same schema can produce fields in
     * a different order run to run, which is noise in a comparison.
     *
     * Everything else the app's schemas use — type, properties, required, items, enum, minItems,
     * maxItems — is supported as is.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function toGeminiSchema(array $schema): array
    {
        $out = [];

        foreach ($schema as $key => $value) {
            if ($key === 'additionalProperties') {
                continue; // rejected by the API — see the docblock
            }

            if ($key === 'properties' && is_array($value)) {
                $properties = [];
                foreach ($value as $name => $property) {
                    $properties[$name] = is_array($property) ? $this->toGeminiSchema($property) : $property;
                }
                $out['properties'] = $properties;
                $out['propertyOrdering'] = array_keys($properties);

                continue;
            }

            $out[$key] = is_array($value) && $key === 'items' ? $this->toGeminiSchema($value) : $value;
        }

        return $out;
    }
}
