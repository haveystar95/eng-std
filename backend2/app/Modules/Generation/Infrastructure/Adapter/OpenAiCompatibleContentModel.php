<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Dto\ModelCallUsage;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * `POST /chat/completions` with `response_format: json_schema` — OpenAI's shape, and xAI's, which
 * implements the same surface deliberately. ONE adapter with two bindings rather than two classes
 * that would have to be kept in step: a divergence between them would show up in a bake-off as a
 * quality difference between vendors, which is exactly the reading the whole exercise must not
 * produce.
 *
 * If xAI ever stops matching OpenAI's request shape, this splits — but it splits on evidence, not
 * on the assumption that two vendors must need two classes.
 *
 * Every call goes through {@see VendorCall}: journalled before it is made, connected within ten seconds, waited for as long
 * as the caller says, retried only on a vendor's answer that clears by itself — never after a timeout (наряд GEN-3). The
 * answer's `usage.prompt_tokens_details.cached_tokens` is read and priced at the cached rate.
 */
final readonly class OpenAiCompatibleContentModel implements ContentModelPort
{
    public function __construct(
        private OutboundCallContext $context,
        private VendorCall $calls,
        private ProviderId $provider,
        private string $apiKey,
        private string $model,
        private string $baseUrl,
        private ModelCost $cost = new ModelCost(),
        private int $timeoutSeconds = 180,
        /**
         * WHAT this spend is FOR, as the request log records it.
         *
         * A parameter rather than a constant because the same adapter now serves two
         * products with different budgets: a collection and a learning plan. The label
         * is applied where the vendor call is made — the log row is written by an Http
         * event listener far from the code that decided to spend — so it has to travel
         * with the adapter, and the default keeps every existing caller unchanged.
         */
        /** What the request log calls this spend; the journal of model calls says the same unless `$journalPurpose` differs. */
        private string $purpose = 'generation',
        /**
         * HOW MANY ATTEMPTS the call may make, the first one included (Laravel's `retry` counts attempts). One
         * is the plan's slot judge (D-28): a learner waits on its answer, and past its timeout the code rules.
         */
        private int $retries = 4,
        /** What the JOURNAL calls this call — finer than the money label when the caller needs it (наряд BACK-TAILS-1 §3.3). */
        private ?string $journalPurpose = null,
    ) {}

    public function provider(): ProviderId
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
    {
        $startedAt = hrtime(true);
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $prompt->text],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'content', 'strict' => true, 'schema' => $schema],
            ],
        ];

        $response = $this->calls->send(
            $this->provider,
            $this->model,
            $this->journalPurpose ?? $this->purpose,
            $this->timeoutSeconds,
            $body,
            // Labelled so the request log can say what this spend was FOR, like every other vendor call.
            fn () => $this->context->run($this->purpose, null, fn () => Http::withToken($this->apiKey)
                ->connectTimeout(VendorCall::CONNECT_TIMEOUT)
                ->timeout($this->timeoutSeconds)
                ->retry($this->retries, VendorCall::backoff(...), VendorCall::retryable(...), throw: false)
                ->post(rtrim($this->baseUrl, '/') . '/chat/completions', $body)),
            fn (Response $r): ModelCallUsage => $this->usage($r),
        );

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new RuntimeException(
                $this->provider->label() . ' API error: ' . $response->status() . ' ' . mb_substr($response->body(), 0, 500)
            );
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException($this->provider->label() . ' returned empty content.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException($this->provider->label() . ' returned malformed JSON: ' . mb_substr($content, 0, 500));
        }

        $usage = $this->usage($response);

        /** @var array<string, mixed> $decoded */
        return new ModelAnswer(
            payload: $decoded,
            model: $usage->answeredModel,
            latencyMs: $latencyMs,
            tokensIn: $usage->tokensIn,
            tokensOut: $usage->tokensOut,
            costUsd: $usage->costUsd,
            raw: mb_substr($content, 0, 4000),
            cachedTokensIn: $usage->cachedTokensIn,
        );
    }

    /** What the answer says it spent: tokens in and out, the cached part of the input, the model that ran, the price. */
    private function usage(Response $response): ModelCallUsage
    {
        $tokensIn = is_int($response->json('usage.prompt_tokens')) ? $response->json('usage.prompt_tokens') : null;
        $cached = is_int($response->json('usage.prompt_tokens_details.cached_tokens')) ? $response->json('usage.prompt_tokens_details.cached_tokens') : null;
        $tokensOut = is_int($response->json('usage.completion_tokens')) ? $response->json('usage.completion_tokens') : null;
        $model = is_string($response->json('model')) ? $response->json('model') : $this->model;

        return new ModelCallUsage($model, $tokensIn, $cached, $tokensOut, $this->cost->estimate($model, $tokensIn, $tokensOut, $cached ?? 0));
    }
}
