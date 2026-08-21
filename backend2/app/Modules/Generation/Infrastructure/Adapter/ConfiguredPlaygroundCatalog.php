<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\PlaygroundProvider;
use App\Modules\Generation\Application\Port\PlaygroundModelCatalog;
use App\Modules\Generation\Application\Port\PlaygroundModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Support\OutboundCallContext;

/**
 * The sandbox registry, built from `config/playground.php`.
 *
 * The model lists are read from config rather than declared here so the OpenAI half stays exactly
 * the set of models this project already runs — see the config file for why that matters. A provider
 * with no key is listed as unavailable WITH THE ENV VAR NAMED, which is the one thing needed to fix
 * it, and never throws: the screen greys the option and says why.
 */
final readonly class ConfiguredPlaygroundCatalog implements PlaygroundModelCatalog
{
    public function __construct(private OutboundCallContext $context) {}

    public function providers(): array
    {
        $out = [];
        foreach (ProviderId::cases() as $provider) {
            $row = $this->row($provider);
            if ($row === null) {
                continue; // A vendor the bake-off knows but the sandbox does not offer.
            }
            $models = $this->models($row);
            if ($models === []) {
                continue;
            }

            $hasKey = trim((string) ($row['key'] ?? '')) !== '';
            $out[] = new PlaygroundProvider(
                provider: $provider->value,
                label: $provider->label(),
                models: $models,
                available: $hasKey,
                reason: $hasKey ? '' : 'нет ключа (' . (string) ($row['env'] ?? '?') . ' не задан)',
            );
        }

        return $out;
    }

    public function get(ProviderId $provider, string $model): ?PlaygroundModelPort
    {
        $row = $this->row($provider);
        if ($row === null) {
            return null;
        }

        $key = trim((string) ($row['key'] ?? ''));
        $model = trim($model);
        // The picker is a closed list, and so is this: an unlisted model name means a typo or a
        // hand-made request, and honouring it would bill a model nobody chose.
        if ($key === '' || ! in_array($model, $this->models($row), true)) {
            return null;
        }

        $timeout = max(1, (int) config('playground.timeout', 60));
        $base = (string) ($row['base_url'] ?? '');

        return match ($provider) {
            ProviderId::Anthropic => new AnthropicPlaygroundModel(
                context: $this->context,
                apiKey: $key,
                model: $model,
                baseUrl: $base,
                timeoutSeconds: $timeout,
            ),
            default => new OpenAiCompatiblePlaygroundModel(
                context: $this->context,
                provider: $provider,
                apiKey: $key,
                model: $model,
                baseUrl: $base,
                timeoutSeconds: $timeout,
            ),
        };
    }

    /** @return array<string, mixed>|null */
    private function row(ProviderId $provider): ?array
    {
        $row = config('playground.providers.' . $provider->value);

        return is_array($row) ? $row : null;
    }

    /**
     * The configured models, blanks and duplicates dropped, order preserved — the config lists the
     * same model under several keys on purpose (the mechanics model IS the enrich model today), and
     * a picker showing it three times would look broken.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function models(array $row): array
    {
        $models = is_array($row['models'] ?? null) ? $row['models'] : [];

        $out = [];
        foreach ($models as $model) {
            if (! is_string($model)) {
                continue;
            }
            $model = trim($model);
            if ($model !== '' && ! in_array($model, $out, true)) {
                $out[] = $model;
            }
        }

        return $out;
    }
}
