# Generation

**Owns:** AI collection generation — requests, prompts, quotas, cost tracking — plus AI-powered
add-ons over a collection: term enrichment, "New example" regeneration, and **realtime
conversation-practice dialogs**.

The app's differentiator: a user types an intent ("иду в банк", "job interview") and gets a
collection of the words and phrases they'd actually need. Treated as a first-class,
**always-async** subsystem.

## Pipeline

```
POST /generations → RequestCollectionGeneration (quota check, insert pending) → 202 {id}
                  → dispatch GenerateCollectionJob
GenerateCollectionJob → ProcessGeneration:
     markRunning → CollectionGeneratorPort::generate (slow, OUTSIDE any tx) — ask ceil(size×1.3)
     → DraftValidator (CEFR/dedup, cap at MAX_ITEMS — size is approximate, never trimmed to `size`)
     → if still short: ONE top-up with an avoid list → merge+dedup+cap (tokens/cost SUMMED)
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
- **Under-delivery:** ask for ~30% more than requested; if still short, one top-up with an avoid
  list closes the gap. Still short after that is an **honest success** — `delivered_count` records
  what actually landed (client shows "13 из 15"), never a failure. Size is approximate (owner
  decision, 2026-08-18): an over-generated batch is never trimmed back down toward `size`, only
  capped at the hard ceiling `MAX_ITEMS` (25).
- **Cost:** `tokens_in/out`, `model`, `cost_usd` recorded per request for the spend read model; a
  top-up's tokens/cost are **summed onto** the primary call, never overwritten.
- **Idempotency:** reprocessing a finished request is a no-op.

## Realtime practice dialogs (premium)

A premium user practises a collection out loud with an AI tutor for one short session. **Audio never
transits this server** — we hand the client an ephemeral realtime token and it streams directly to
the provider; the server only issues the lesson + token, ingests transcripts, scores coverage, and
estimates cost. Two providers behind one port, chosen by `PRACTICE_DRIVER=openai|gemini|fake`; the
start response carries `provider` + `endpoint` so the client knows where to connect.

```
POST /practice/dialogs           → premium gate (403) · daily limit (429 + resets_at) ·
                                    assemble lesson (topic=collection, level=CEFR, native=source_lang,
                                    target=target_lang, ≤8 target words: due→new→known) ·
                                    mint ephemeral token (TTL = session duration) → 201
POST /practice/dialogs/{id}/transcripts → append-only (idempotent by (role, ts)); returns target-word
                                    coverage (normalised occurrence of a term's forms in USER lines)
POST /practice/dialogs/{id}/finish → estimated realtime cost recorded · native-language recap
                                    (one cheap text call) → {summary, words_used, words_total}
GET  /practice/collections/{id}/last-dialog → the collection's last finished|expired dialog's
                                    result {finished_at, words_used, words_total, summary} | 404
```

- **Prompt v3** (`practice_dialog.v3.md`, flipped via `PRACTICE_PROMPT_VERSION`): the agent opens the
  conversation itself; multi-word phrases/questions the agent voices, single words the learner must
  produce (coverage matches that split — **multi-word counts from ANY speaker, single words only from
  the learner**). v3 adds **hard per-CEFR speech rules** injected by level (A1/A2 short simple
  sentences, no contractions, slow; B1/B2 natural; C1+ unrestricted) — the model drifts up a level
  without them. A1/A2 also get a slower playback `speed` (OpenAI session param; prompt sets pace too).
- **Session config** (minted): input-audio transcription (required, or the learner's speech never
  returns as transcript events) + server-VAD turn detection tuned via `PRACTICE_VAD_*`.

- **Ports:** `RealtimeTokenPort` (mint ephemeral token) with three adapters — `OpenAiRealtimeTokenMinter`,
  `GeminiLiveTokenMinter`, and the parameterised `FakeRealtimeTokenMinter` — chosen by `PRACTICE_DRIVER`;
  and `DialogSummarizerPort` (recap; always OpenAI text, driver-independent). Instructions render from the
  versioned prompt file. **Gemini caveat:** the live `auth_tokens` endpoint currently rejects
  `liveConnectConstraints`, so a bare token is minted (real key still server-side) and the lesson is set
  client-side; `PRACTICE_GEMINI_CONSTRAINED=true` bakes it into the token once the field deploys.
- **Coverage** reuses the Learning grader's normalisation via the shared
  `Shared\Domain\Service\LexicalNormalizer` — one definition of "the same words", never a copy.
- **Cross-module (Application only):** Identity (tier + CEFR), Collections (collection + language
  pair), Learning (due/started state → target-word priority), Vocabulary (accepted forms).
- **This is PRACTICE:** it never writes a review or `(user, term)` progress. Its only spend record is
  the *estimated* realtime `cost_usd`, written when the dialog leaves `active` (finish or expiry).
- **Cost** is an estimate (`PracticeCostEstimator` + `ModelCost::estimateRealtime`), calibrated to the
  vendors' **documented token formulas** — not to Google/OpenAI balance deltas (those lag and batch;
  verify against a day's total, not a single call). audio seconds → tokens at the documented rates
  (OpenAI 10 in / 20 out tokens/sec; Gemini 25/25), priced per 1M (OpenAI 2.1-mini $10/$20; Gemini Live
  $3/$12), plus the tiny text transcript. Audio seconds are bounded by the **real transcript span** (so
  an expired / long-TTL dialog is billed for the actual call, not the whole TTL); OUTPUT audio is
  further capped to the agent's speaking time (~150 wpm from its transcript). No usage event reaches us.
- **Config:** `PRACTICE_DRIVER` (openai|gemini|fake), `PRACTICE_REALTIME_MODEL` (`gpt-realtime-2.1-mini`),
  `PRACTICE_GEMINI_MODEL` (`gemini-3.1-flash-live-preview`), `PRACTICE_GEMINI_CONSTRAINED` (false),
  `PRACTICE_REALTIME_TRANSCRIBE_MODEL` (`gpt-4o-mini-transcribe`), `PRACTICE_REALTIME_VOICE`,
  `PRACTICE_PROMPT_VERSION` (`v3`), `PRACTICE_SLOW_SPEED` (0.9), `PRACTICE_VAD_*` (900 / 0.5 / 300),
  `PRACTICE_DIALOG_TTL_SECONDS` (200), `PRACTICE_DIALOGS_PER_DAY` (5), `PRACTICE_MAX_TARGET_WORDS` (8).
  Keys: `OPENAI_API_KEY`, `GEMINI_API_KEY` (both server-side only).
- **Dev commands:** `php artisan practice:grant-premium {email}` (self-test premium) and
  `php artisan practice:smoke` (mint a real token on the live key, no audio, print a cost line).

**Deferred:** embedding/semantic dedup, prompt-cache reuse by normalized prompt, language
detection, push notifications (the client polls). See `.claude/skills/ai-collection-generation`.
