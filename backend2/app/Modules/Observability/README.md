# Observability

**Owns:** the API request/response log — every inbound call to our API and every outbound
call to an external service (OpenAI today), in one `api_request_logs` table.

A technical, cross-cutting module: it spans all the business modules and belongs to none,
so it lives on its own rather than polluting a bounded context or the `Shared` kernel. It
is deliberately thin — one pure domain rule, no aggregates.

## Layers

- `Domain/Service/SecretRedactor` — pure, recursive redaction of secret-bearing keys
  (`id_token`, bearer/`token`, `authorization`, `api_key`, `password`, `cookie`, …) **and of
  credential-shaped values** under innocent keys (`auth_tokens/…`, `sk-…`, `ek_…`, `Bearer …`) —
  Gemini returns its minted ephemeral token as `name`, which key-matching alone let through. The
  module's single invariant: **credentials never reach the log table.**
- `Application` — `ApiLogEntry` (DTO), `ApiLogWriter` (port), and `Support/OutboundCallContext`.
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

## Labelling outbound spend (`purpose` / `collection_id`)

The row is written by an Http-client event listener, far from the code that decided to call
OpenAI, so *why* a call happened cannot be passed as an argument — it has to be ambient.
`OutboundCallContext` is that scope: a caller wraps its work in `run(purpose, collectionId, …)`
and every outbound call inside is tagged. Frames nest and inherit, and `run()` always pops.

`purpose` ∈ `generation | images | enrichment | realtime | recap | example_regen`.

Collection generation is the awkward one: the model call is paid for *before* the collection it
creates exists. The writer therefore reports each written id back to the context, and the
generation job calls `attachCollection()` once the id is known, which stamps those rows.

Money itself is **not** counted from this table — the spend ledgers (`generation_requests`,
`practice_dialogs`, `term_enrichments`, `example_regenerations`) are the financial record and
cover the whole history. The log is forensics: which call, with what body, how long, how much did
*that one* cost. Its per-row price is derived from the stored usage block using the same
`Shared\Domain\Service\ModelCost` table the ledgers are written with, so the two can't drift.
