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

    /**
     * Paths the log never records: the back-office itself.
     *
     * This log exists to show what the PRODUCT did — what the app asked for, what we asked the
     * vendors. The panel is the instrument, not the traffic, and recording it breaks the instrument
     * three ways at once:
     *
     *  - it observes itself. Opening `admin/api/logs/{id}` writes a new row whose `response_body`
     *    IS the row just opened — so the reader sees an empty request body (a GET has none) beside
     *    a response body containing somebody else's request body, and concludes the two are mixed
     *    up. They are not; it is a log of looking at a log.
     *  - it steals the columns. `model` is read as `COALESCE(response_body->>'model', …)`, so a row
     *    that merely CONTAINS an OpenAI record inherits its model — an admin GET displayed as
     *    `gpt-4o-mini-2024-07-18`, which is a plain lie about who called what.
     *  - it buries the evidence. One pass over the panel writes dozens of rows, and the newest-first
     *    list pushes the app and provider calls — the reason anyone opened this screen — off page one.
     *
     * Nothing auditable is lost: admin MUTATIONS record themselves in `admin_audit_log`, which is
     * where "who changed what" belongs, and reads have no audit value at all.
     */
    private const IGNORED_PATHS = ['admin/api', 'admin/api/*'];

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
        if ($request->is(...self::IGNORED_PATHS)) {
            return;
        }

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
                purpose: null,          // purpose/collection label outbound spend only
                collectionId: null,
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
