<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Listener;

use App\Modules\Observability\Application\Dto\ApiLogEntry;
use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Observability\Domain\Service\SecretRedactor;
use DateTimeImmutable;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Records every outbound HTTP call the app makes (via Laravel's Http client) — today
 * that's OpenAI generation. Hooks the client's own events so any future integration is
 * logged automatically, with the Authorization header / api_key redacted.
 */
final class LogOutboundHttp
{
    private const MAX_RAW = 8000;

    public function __construct(
        private readonly ApiLogWriter $writer,
        private readonly SecretRedactor $redactor,
        private readonly OutboundCallContext $context,
    ) {}

    public function handleResponse(ResponseReceived $event): void
    {
        $this->record($event->request, $event->response, null);
    }

    public function handleFailure(ConnectionFailed $event): void
    {
        $this->record($event->request, null, $event->exception->getMessage());
    }

    private function record(Request $request, ?Response $response, ?string $error): void
    {
        try {
            $url = $request->url();
            $host = parse_url($url, PHP_URL_HOST);
            $path = parse_url($url, PHP_URL_PATH);

            $id = $this->writer->write(new ApiLogEntry(
                direction: 'outbound',
                method: $request->method(),
                path: is_string($path) ? $path : '/',
                host: is_string($host) ? $host : null,
                service: $this->serviceFor(is_string($host) ? $host : ''),
                purpose: $this->context->purpose(),
                collectionId: $this->context->collectionId(),
                status: $response?->status(),
                durationMs: $this->durationMs($response),
                userId: null,
                requestBytes: strlen($request->body()),
                responseBytes: $response !== null ? strlen($response->body()) : null,
                requestHeaders: $this->redactor->redact($request->headers()),
                requestBody: $this->decode($request->body()),
                responseBody: $response !== null ? $this->decode($response->body()) : null,
                error: $error,
                occurredAt: new DateTimeImmutable(),
            ));

            // Report back so a collection that only becomes known after the call (generation) can
            // still be stamped on this row.
            if ($id !== null) {
                $this->context->record($id);
            }
        } catch (Throwable) {
            // Observability must never break the call it is observing.
        }
    }

    private function serviceFor(string $host): ?string
    {
        return str_contains($host, 'openai') ? 'openai' : null;
    }

    private function durationMs(?Response $response): ?int
    {
        if ($response === null) {
            return null;
        }
        $total = $response->handlerStats()['total_time'] ?? null;

        return is_numeric($total) ? (int) round(((float) $total) * 1000) : null;
    }

    /** @return array<mixed>|null */
    private function decode(string $body): ?array
    {
        if ($body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $this->redactor->redact($decoded);
        }

        return ['raw' => mb_substr($body, 0, self::MAX_RAW)];
    }
}
