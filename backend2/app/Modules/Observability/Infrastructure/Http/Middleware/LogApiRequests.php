<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Http\Middleware;

use App\Modules\Observability\Application\Dto\ApiLogEntry;
use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Observability\Domain\Service\SecretRedactor;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every inbound API request as one row, written in `terminate()` — after the
 * response is already flushed to the client, so logging adds no perceptible latency.
 * Request/response bodies and headers are redacted before they are handed to the writer.
 */
final class LogApiRequests
{
    private const MAX_RAW = 8000;

    public function __construct(
        private readonly ApiLogWriter $writer,
        private readonly SecretRedactor $redactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('obs_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $started = $request->attributes->get('obs_started_at');
            $durationMs = is_float($started) ? (int) round((microtime(true) - $started) * 1000) : null;

            $requestBody = $this->redactor->redact($request->all());
            $content = $this->responseContent($response);

            $this->writer->write(new ApiLogEntry(
                direction: 'inbound',
                method: $request->getMethod(),
                path: $request->path(),
                host: $request->getHost(),
                service: null,
                status: $response->getStatusCode(),
                durationMs: $durationMs,
                userId: $this->userId($request),
                requestBytes: strlen((string) $request->getContent()),
                responseBytes: $content !== null ? strlen($content) : null,
                requestHeaders: $this->redactor->redact($request->headers->all()),
                requestBody: $requestBody === [] ? null : $requestBody,
                responseBody: $this->decodeBody($content),
                error: null,
                occurredAt: new DateTimeImmutable(),
            ));
        } catch (Throwable) {
            // Never let logging surface an error to the client.
        }
    }

    private function userId(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id !== null ? (string) $id : null;
    }

    private function responseContent(Response $response): ?string
    {
        $content = $response->getContent();

        return $content === false || $content === '' ? null : $content;
    }

    /** @return array<mixed>|null */
    private function decodeBody(?string $content): ?array
    {
        if ($content === null) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $this->redactor->redact($decoded);
        }

        return ['raw' => mb_substr($content, 0, self::MAX_RAW)];
    }
}
