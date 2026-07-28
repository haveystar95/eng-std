# Observability

**Owns:** the API request/response log — every inbound call to our API and every outbound
call to an external service (OpenAI today), in one `api_request_logs` table.

A technical, cross-cutting module: it spans all the business modules and belongs to none,
so it lives on its own rather than polluting a bounded context or the `Shared` kernel. It
is deliberately thin — one pure domain rule, no aggregates.

## Layers

- `Domain/Service/SecretRedactor` — pure, recursive redaction of secret-bearing keys
  (`id_token`, bearer/`token`, `authorization`, `api_key`, `password`, `cookie`, …). The
  module's single invariant: **credentials never reach the log table.**
- `Application` — `ApiLogEntry` (DTO) + `ApiLogWriter` (port).
- `Infrastructure`
  - `Http/Middleware/LogApiRequests` — terminable middleware (pushed onto the `api` group);
    writes the inbound row *after* the response is flushed, so it adds no latency.
  - `Listener/LogOutboundHttp` — subscribes to the Http client's `ResponseReceived` /
    `ConnectionFailed` events; logs outbound calls automatically.
  - `Eloquent/{ApiRequestLogModel, EloquentApiLogWriter}` — the sink. The writer never
    throws into the caller (observability must not break the thing it observes).

## What's captured

Metadata (method, path/host, status, duration, user, byte sizes, transport error) plus the
request/response **bodies and headers, redacted**. Non-JSON bodies are stored truncated as
`{ "raw": … }`. There is no HTTP surface — this module only writes.
