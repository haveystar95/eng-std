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
 * show it. So we default to a BARE token and return a pre-rendered `BidiGenerateContentSetup` (system
 * instruction v3 + model + transcription + VAD) as the token's `sessionSetup`; the client applies it
 * verbatim as its first WebSocket message, rendering nothing itself. When the constraints field
 * deploys, flip PRACTICE_GEMINI_CONSTRAINED=true and the lesson is baked into the token instead.
 */
final class GeminiLiveTokenMinter implements RealtimeTokenPort
{
    /**
     * WebSocket endpoint the client connects to with the ephemeral token. Ephemeral tokens are only
     * accepted on the **v1alpha BidiGenerateContentConstrained** service, passed as the
     * `?access_token=<token>` query parameter — the standard v1beta BidiGenerateContent service takes
     * only API keys/OAuth and rejects an ephemeral token with a 1008 close. (The client appends
     * `?access_token=` using the `realtime_token` from the start response.)
     * Verified against the Google AI forum thread on unregistered WebSocket callers + the ephemeral
     * tokens docs.
     */
    private const WS_ENDPOINT = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained';

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
            // With a bare token the client must send the setup itself — hand it the rendered one so
            // it applies it verbatim. With a constrained token it's already baked in, so: null.
            sessionSetup: $this->constrained ? null : $this->sessionSetup($spec),
        );
    }

    /**
     * The `BidiGenerateContentSetup` the client sends as its first WebSocket message: the versioned
     * system instruction (with this lesson's CEFR rules), the model, response modality, both-sides
     * transcription, and VAD. The client applies it as-is.
     *
     * @return array<string, mixed>
     */
    private function sessionSetup(RealtimeSessionSpec $spec): array
    {
        return [
            'model' => 'models/' . $spec->model,
            'generationConfig' => ['responseModalities' => ['AUDIO']],
            'systemInstruction' => ['parts' => [['text' => $this->renderInstructions($spec)]]],
            'inputAudioTranscription' => (object) [],
            'outputAudioTranscription' => (object) [],
            'realtimeInputConfig' => [
                'automaticActivityDetection' => [
                    'prefixPaddingMs' => $spec->vad->prefixPaddingMs,
                    'silenceDurationMs' => $spec->vad->silenceMs,
                ],
            ],
        ];
    }

    private function renderInstructions(RealtimeSessionSpec $spec): string
    {
        $template = (string) file_get_contents(__DIR__ . "/../Prompt/practice_dialog.{$this->promptVersion}.md");

        return $this->instructions->render($template, $spec->lesson);
    }

    /**
     * The lesson baked into the token (used only when {@see $constrained}). System instruction from
     * the versioned prompt, both-sides transcription, and VAD via automaticActivityDetection.
     *
     * @return array<string, mixed>
     */
    private function constraints(RealtimeSessionSpec $spec): array
    {
        return [
            'model' => 'models/' . $spec->model,
            'config' => [
                'responseModalities' => ['AUDIO'],
                'systemInstruction' => ['parts' => [['text' => $this->renderInstructions($spec)]]],
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
