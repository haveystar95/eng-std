<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\RealtimeSessionSpec;
use App\Modules\Generation\Application\Dto\RealtimeToken;
use App\Modules\Generation\Application\Port\RealtimeTokenPort;
use App\Modules\Generation\Infrastructure\Prompt\PracticeDialogInstructions;
use App\Modules\Shared\Domain\Service\Clock;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mints an ephemeral auth token for the Gemini Live API via `POST /v1beta/auth_tokens`. The standing
 * GEMINI_API_KEY stays server-side; the returned short-lived token `name` is what the client uses to
 * open a Live WebSocket. Docs: ai.google.dev/gemini-api/docs/live-api/ephemeral-tokens.
 *
 * NOTE (verified live 2026-08): the constrained form — baking the lesson into
 * `liveConnectConstraints.config` (as OpenAI bakes instructions into its minted session) — is
 * rejected by the current deployment with `Unknown name "liveConnectConstraints"`, though the docs
 * show it. So we default to a BARE token; the lesson's system instruction is then set by the client
 * in its BidiGenerateContentSetup. When the field deploys, flip PRACTICE_GEMINI_CONSTRAINED=true and
 * the rendered lesson is attached to the token instead.
 */
final class GeminiLiveTokenMinter implements RealtimeTokenPort
{
    /** Raw Live API WebSocket endpoint the client connects to with the ephemeral token. */
    private const WS_ENDPOINT = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent';

    public function __construct(
        private readonly string $apiKey,
        private readonly PracticeDialogInstructions $instructions,
        private readonly Clock $clock,
        private readonly string $promptVersion = 'v3',
        private readonly bool $constrained = false,
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
    ) {}

    public function mint(RealtimeSessionSpec $spec): RealtimeToken
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        // Token validity = the session window; the client must start the session within a short grace.
        $expireTime = $now->modify("+{$spec->ttlSeconds} seconds");
        $startBy = $now->modify('+' . min($spec->ttlSeconds, 120) . ' seconds');

        $body = [
            'uses' => 1,
            'expireTime' => $expireTime->format('Y-m-d\TH:i:s\Z'),
            'newSessionExpireTime' => $startBy->format('Y-m-d\TH:i:s\Z'),
        ];

        if ($this->constrained) {
            $body['liveConnectConstraints'] = $this->constraints($spec);
        }

        $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout(30)
            ->post(rtrim($this->baseUrl, '/') . '/auth_tokens', $body);

        if ($response->failed()) {
            throw new RuntimeException('Gemini auth-token error: ' . $response->status() . ' ' . $response->body());
        }

        // The token string is the returned resource `name` (e.g. "auth_tokens/...").
        $name = $response->json('name');
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('Gemini auth-token response had no name: ' . $response->body());
        }

        return new RealtimeToken(
            value: $name,
            expiresAt: $expireTime,
            model: $spec->model,
            provider: 'gemini',
            endpoint: self::WS_ENDPOINT,
        );
    }

    /**
     * The lesson baked into the token (used only when {@see $constrained}). System instruction from
     * the versioned prompt, both-sides transcription, and VAD via automaticActivityDetection.
     *
     * @return array<string, mixed>
     */
    private function constraints(RealtimeSessionSpec $spec): array
    {
        $template = (string) file_get_contents(__DIR__ . "/../Prompt/practice_dialog.{$this->promptVersion}.md");
        $instructions = $this->instructions->render($template, $spec->lesson);

        return [
            'model' => 'models/' . $spec->model,
            'config' => [
                'responseModalities' => ['AUDIO'],
                'systemInstruction' => ['parts' => [['text' => $instructions]]],
                'inputAudioTranscription' => (object) [],
                'outputAudioTranscription' => (object) [],
                'realtimeInputConfig' => [
                    'automaticActivityDetection' => [
                        'prefixPaddingMs' => $spec->vad->prefixPaddingMs,
                        'silenceDurationMs' => $spec->vad->silenceMs,
                    ],
                ],
            ],
        ];
    }
}
