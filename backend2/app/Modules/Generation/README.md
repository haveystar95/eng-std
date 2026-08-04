# Generation

**Owns:** AI collection generation — requests, prompts, quotas, cost tracking

The app's differentiator: a user types an intent ("иду в банк", "job interview") and gets a
collection of the words and phrases they'd actually need. Treated as a first-class,
**always-async** subsystem.

## Pipeline

```
POST /generations → RequestCollectionGeneration (quota check, insert pending) → 202 {id}
                  → dispatch GenerateCollectionJob
GenerateCollectionJob → ProcessGeneration:
     markRunning → CollectionGeneratorPort::generate (slow, OUTSIDE any tx) — ask ceil(size×1.3)
     → DraftValidator (CEFR/dedup, trim to requested)
     → if still short: ONE top-up with an avoid list → merge+dedup+trim (tokens/cost SUMMED)
     → [tx: CreateGeneratedCollection + ImportTerm×N + AddTerm]
     → markSucceeded (collection_id, tokens, cost, delivered_count)
GET /generations/{id} → the client polls until succeeded|failed; reads `requested`/`delivered`
```

The console command `php artisan generation:make {user} {prompt}` runs the same
`ProcessGeneration` **synchronously** and prints the result — no queue, handy from the terminal.

## The port

`Application/Port/CollectionGeneratorPort` is the only seam to the LLM.
`Infrastructure/Adapter/OpenAiCollectionGenerator` (structured outputs, strict JSON schema)
is the real one; `FakeCollectionGenerator` is deterministic and bound when
`GENERATION_DRIVER=fake` (tests, and local dev without a key). Prompts are versioned files
(`Infrastructure/Prompt/generate_collection.v1.md`), never inline; the user's prompt is
passed as delimited data, never as instructions.

## Boundaries

Generation never touches other modules' tables or Domain. It calls, through Application only:
- Vocabulary `ImportTerm` — a primitive-typed shim (so Generation doesn't import Vocabulary's
  Domain VOs); dedup still happens via `FindOrCreateTerm`. Generated terms carry `source='ai'`.
- Collections `CreateGeneratedCollection` (source=ai, owned, private) + `AddTermToCollection`.

## Rules that live here

- **Quota:** a daily cap counted as today's non-failed requests — a failure refunds itself.
  Exceeding it throws `GenerationQuotaExceeded` → 429.
- **Validation:** reject a *primary* draft with too few usable items; drop out-of-level, empty and
  duplicate items; cap at 25. A truncated primary draft is a terminal failure (no retry), not a
  shipped broken set. A **top-up** batch is supplemental — it skips the floor (2 fresh items is fine).
- **Under-delivery:** ask for ~30% more than requested and trim back; if still short, one top-up
  with an avoid list closes the gap. Still short after that is an **honest success** — `delivered_count`
  records what actually landed (client shows "13 из 15"), never a failure.
- **Cost:** `tokens_in/out`, `model`, `cost_usd` recorded per request for the spend read model; a
  top-up's tokens/cost are **summed onto** the primary call, never overwritten.
- **Idempotency:** reprocessing a finished request is a no-op.

**Deferred:** embedding/semantic dedup, prompt-cache reuse by normalized prompt, language
detection, push notifications (the client polls). See `.claude/skills/ai-collection-generation`.
