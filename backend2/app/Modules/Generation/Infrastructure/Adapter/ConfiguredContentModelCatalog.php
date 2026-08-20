<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ProviderAvailability;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Support\OutboundCallContext;

/**
 * The three vendors, built from config. A provider whose key is empty is reported as unavailable
 * with the env var named — the one thing a person reading a bake-off at 8am needs in order to fix it.
 *
 * The key lookup is per provider and never falls back to another vendor's: an `OPENAI_API_KEY`
 * silently used as an xAI key produces a 401 that reads like an outage.
 */
final readonly class ConfiguredContentModelCatalog implements ContentModelCatalog
{
    /** @var array<string, array{key: string, model: string, base: string, env: string, default_model: string}> */
    private array $config;

    /**
     * Per-call timeout, shared by every adapter so one vendor is never given a longer rope than
     * another by accident — a comparison where one model got three minutes and another got one is
     * measuring the timeouts.
     */
    private int $timeoutSeconds;

    public function __construct(private OutboundCallContext $context)
    {
        $this->timeoutSeconds = max(1, (int) config('services.generation.model_timeout', 180));

        $this->config = [
            ProviderId::OpenAi->value => [
                'key' => (string) config('services.openai.api_key'),
                // `compare_model`, NOT `generate_model` — see config/services.php: production and
                // this comparison must be able to run different models.
                'model' => (string) config('services.openai.compare_model', 'gpt-4o'),
                'base' => 'https://api.openai.com/v1',
                'env' => 'OPENAI_API_KEY',
                'default_model' => 'gpt-4o',
            ],
            ProviderId::Anthropic->value => [
                'key' => (string) config('services.anthropic.api_key'),
                'model' => (string) config('services.anthropic.generate_model', 'claude-opus-5'),
                'base' => 'https://api.anthropic.com/v1',
                'env' => 'ANTHROPIC_API_KEY',
                'default_model' => 'claude-opus-5',
            ],
            ProviderId::Xai->value => [
                'key' => (string) config('services.xai.api_key'),
                'model' => (string) config('services.xai.generate_model', 'grok-4.6'),
                'base' => (string) config('services.xai.base_url', 'https://api.x.ai/v1'),
                'env' => 'GROK_API_KEY',
                'default_model' => 'grok-4.6',
            ],
            ProviderId::Gemini->value => [
                'key' => (string) config('services.gemini.api_key'),
                'model' => (string) config('services.gemini.generate_model', 'gemini-3.7-flash'),
                'base' => 'https://generativelanguage.googleapis.com/v1beta',
                'env' => 'GEMINI_API_KEY',
                'default_model' => 'gemini-3.7-flash',
            ],
        ];
    }

    public function availability(): array
    {
        $out = [];
        foreach (ProviderId::cases() as $provider) {
            $row = $this->config[$provider->value];
            $model = $row['model'] !== '' ? $row['model'] : $row['default_model'];
            $out[] = trim($row['key']) !== ''
                ? ProviderAvailability::ready($provider, $model)
                : ProviderAvailability::missingKey($provider, $model, $row['env']);
        }

        return $out;
    }

    public function available(): array
    {
        $out = [];
        foreach (ProviderId::cases() as $provider) {
            $port = $this->get($provider);
            if ($port !== null) {
                $out[] = $port;
            }
        }

        return $out;
    }

    public function get(ProviderId $provider, ?string $model = null): ?ContentModelPort
    {
        $row = $this->config[$provider->value];
        $key = trim($row['key']);
        if ($key === '') {
            return null;
        }

        $model = $model !== null && trim($model) !== ''
            ? trim($model)
            : ($row['model'] !== '' ? $row['model'] : $row['default_model']);

        return match ($provider) {
            ProviderId::Gemini => new GeminiContentModel(
                context: $this->context,
                apiKey: $key,
                model: $model,
                baseUrl: $row['base'],
                timeoutSeconds: $this->timeoutSeconds,
            ),
            ProviderId::Anthropic => new AnthropicContentModel(
                context: $this->context,
                apiKey: $key,
                model: $model,
                baseUrl: $row['base'],
                timeoutSeconds: $this->timeoutSeconds,
            ),
            // OpenAI and xAI speak the same wire format — see OpenAiCompatibleContentModel.
            ProviderId::OpenAi, ProviderId::Xai => new OpenAiCompatibleContentModel(
                context: $this->context,
                provider: $provider,
                apiKey: $key,
                model: $model,
                baseUrl: $row['base'],
                timeoutSeconds: $this->timeoutSeconds,
            ),
        };
    }
}
