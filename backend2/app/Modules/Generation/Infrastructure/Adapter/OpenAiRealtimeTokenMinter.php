<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Dto\RealtimeToken;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Infrastructure\Prompt\PracticeDialogInstructions;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mints an ephemeral realtime client secret via OpenAI's `POST /v1/realtime/client_secrets`. The
 * lesson is rendered into the session `instructions` from the versioned prompt file, and the token
 * is set to expire after the spec's TTL — that TTL is the whole duration guard for the session.
 *
 * The standing API key is used server-side only; the returned short-lived `value` is what the app
 * receives and uses to open the WebRTC connection directly to OpenAI.
 */
final class OpenAiRealtimeTokenMinter implements RealtimeTokenPort
{
    public function __construct(
        private readonly string $apiKey,
        private readonly PracticeDialogInstructions $instructions,
        private readonly string $promptVersion = 'v1',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function mint(RealtimeSessionSpec $spec): RealtimeToken
    {
        $template = (string) file_get_contents(__DIR__ . "/../Prompt/practice_dialog.{$this->promptVersion}.md");
        $instructions = $this->instructions->render($template, $spec->lesson);

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post(rtrim($this->baseUrl, '/') . '/realtime/client_secrets', [
                'expires_after' => ['anchor' => 'created_at', 'seconds' => $spec->ttlSeconds],
                'session' => [
                    'type' => 'realtime',
                    'model' => $spec->model,
                    'instructions' => $instructions,
                    'audio' => ['output' => ['voice' => $spec->voice]],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI realtime mint error: ' . $response->status() . ' ' . $response->body());
        }

        // The API has shipped both a flat `value` and a nested `client_secret.value`; accept either.
        $value = $response->json('value') ?? $response->json('client_secret.value');
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('OpenAI realtime mint returned no client secret: ' . $response->body());
        }

        $expiresAt = $response->json('expires_at') ?? $response->json('client_secret.expires_at');
        if (! is_int($expiresAt)) {
            throw new RuntimeException('OpenAI realtime mint returned no expiry: ' . $response->body());
        }

        return new RealtimeToken(
            value: $value,
            expiresAt: (new DateTimeImmutable())->setTimestamp($expiresAt),
            model: $spec->model,
        );
    }
}
