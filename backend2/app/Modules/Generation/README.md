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

## Realtime practice dialogs (premium)

A premium user practises a collection out loud with an AI tutor for one short session. **Audio never
transits this server** — we hand the client an ephemeral OpenAI Realtime token and it streams over
WebRTC directly to OpenAI; the server only issues the lesson + token, ingests transcripts, scores
coverage, and estimates cost.

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

- **Prompt v2** (`practice_dialog.v2.md`, flipped via `PRACTICE_PROMPT_VERSION`): the agent opens the
  conversation itself; multi-word target phrases/questions the agent voices, single target words the
  learner must produce. Coverage matches that split — **multi-word terms count from ANY speaker,
  single words only from the learner**.
- **Session config** (minted): input-audio transcription model (required, or the learner's speech
  never returns as transcript events) + server-VAD turn detection tuned via `PRACTICE_VAD_*` so it
  doesn't cut the learner off.

- **Ports:** `RealtimeTokenPort` (mint ephemeral secret; `OpenAiRealtimeTokenMinter` + fake) and
  `DialogSummarizerPort` (recap; `OpenAiDialogSummarizer` + fake), gated by `PRACTICE_DRIVER`.
  Instructions come from the versioned `Infrastructure/Prompt/practice_dialog.v1.md`.
- **Coverage** reuses the Learning grader's normalisation via the shared
  `Shared\Domain\Service\LexicalNormalizer` — one definition of "the same words", never a copy.
- **Cross-module (Application only):** Identity (tier + CEFR), Collections (collection + language
  pair), Learning (due/started state → target-word priority), Vocabulary (accepted forms).
- **This is PRACTICE:** it never writes a review or `(user, term)` progress. Its only spend record is
  the *estimated* realtime `cost_usd`, written when the dialog leaves `active` (finish or expiry).
- **Cost** is an estimate (`ModelCost::estimateRealtime`): billed seconds → audio tokens at OpenAI's
  documented rates (10 in / 20 out tokens per second), priced per 1M ($10/$20 for 2.1-mini), plus the
  tiny text transcript. No usage event reaches us, so it's a proxy — tune the per-second assumption or
  rates as real spend shows.
- **Config:** `PRACTICE_REALTIME_MODEL` (default `gpt-realtime-2.1-mini`), `PRACTICE_REALTIME_TRANSCRIBE_MODEL`
  (`gpt-4o-mini-transcribe`), `PRACTICE_REALTIME_VOICE`, `PRACTICE_PROMPT_VERSION` (`v2`), `PRACTICE_VAD_*`
  (silence 900ms / threshold 0.5 / prefix 300ms), `PRACTICE_DIALOG_TTL_SECONDS` (200),
  `PRACTICE_DIALOGS_PER_DAY` (5), `PRACTICE_MAX_TARGET_WORDS` (8).
- **Dev commands:** `php artisan practice:grant-premium {email}` (self-test premium) and
  `php artisan practice:smoke` (mint a real token on the live key, no audio, print a cost line).

**Deferred:** embedding/semantic dedup, prompt-cache reuse by normalized prompt, language
detection, push notifications (the client polls). See `.claude/skills/ai-collection-generation`.
