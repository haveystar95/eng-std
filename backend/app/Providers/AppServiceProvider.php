<?php

namespace App\Providers;

use App\Services\Ai\AiProvider;
use App\Services\Ai\ClaudeProvider;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\GoogleAuthenticator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleAuthenticator::class, function () {
            return new GoogleAuthenticator(config('services.google.client_ids', []));
        });

        // Swap the AI backend via AI_PROVIDER (claude | ollama).
        $this->app->singleton(AiProvider::class, function () {
            $provider = config('services.ai.provider', 'claude');

            return match ($provider) {
                'openai' => new OpenAiProvider(
                    apiKey: (string) config('services.openai.api_key'),
                    generateModel: (string) config('services.openai.generate_model'),
                    checkModel: (string) config('services.openai.check_model'),
                ),
                'ollama' => new OllamaProvider(
                    baseUrl: (string) config('services.ollama.url'),
                    model: (string) config('services.ollama.model'),
                ),
                default => new ClaudeProvider(
                    apiKey: (string) config('services.claude.api_key'),
                    generateModel: (string) config('services.claude.generate_model'),
                    checkModel: (string) config('services.claude.check_model'),
                ),
            };
        });
    }

    public function boot(): void
    {
        // API returns bare JSON (no top-level "data" wrapper).
        JsonResource::withoutWrapping();
    }
}
