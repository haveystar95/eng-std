# Generation

**Owns:** AI collection generation — requests, prompts, quotas, cost tracking — plus AI-powered
add-ons over a collection: term enrichment, "New example" regeneration, **realtime
conversation-practice dialogs**, and **word search** (`/search*`).

The app's differentiator: a user types an intent ("иду в банк", "job interview") and gets a
collection of the words and phrases they'd actually need. Treated as a first-class,
**always-async** subsystem.

## Word search — why it lives here

`GET /search` costs nothing and reads Vocabulary; `POST /search/lookup` spends money; `POST
/search/add` writes a term, puts it in a folder and enrols it. Three modules' data, one feature.

It is Generation's because of what the free half is the first half OF: the whole reason to run the
database search is to decide whether to buy a lookup, so «found / not found» is a spending
decision, and the two halves must normalise a query identically or the cache key drifts from the
search. Generation's Application is also the only layer deptrac already lets reach all four
collaborators it needs (Vocabulary, Collections, Learning, Identity) — no new boundary was opened
for this.

The economics, stated once:

- **cache → cap → call.** A cached answer is free and does not consult the cap. `search_lookups` is
  keyed on (normalized query, target lang, native lang) and is GLOBAL — the second learner to type a
  word pays nothing, forever. `user_id` on the row is *who paid*, and it exists so the cap has
  something to count.
- **`SEARCH_LOOKUP_DAILY_CAP`** (default 30/user/day) is a runaway guard, not a plan feature. A spent
  cap is a **200** with `limit_reached: true` — the app shows the free results beside it.
- **the cheap model**, by decision (`OPENAI_SEARCH_MODEL`, gpt-4o-mini). A lookup is a dictionary
  entry; the strong model costs ~200× more for an answer nobody can tell apart.
- **`LookupBarrier`** is `LanguageBarrier`'s shape for a synchronous call: no repair pass (the
  learner is watching a spinner), so an unstorable answer is refused outright. One thing degrades
  instead of refusing — an example that does not contain its term is dropped and the card is served
  without it.
- **`DescriptionSelfReference`** is the rule the `description_match` trainer depends on: a
  description containing its own headword answers the card before it is asked.

### The instant hint (`GET /search/instant`)

A machine translation shown under the search field while the learner is still typing — a garnish on
top of the lookup, never a replacement for it. Three rungs, each reached only because the one above
had nothing: **our own catalogue** (free, and the exact string the word's card will show), **the
shared cache** (`instant_translations`, bought once for everybody), **the vendor** (DeepL, behind
`TranslationProvider`). On a debounced field the same few hundred words are typed over and over, so
the first two rungs answer nearly everything and the free plan stretches far past the request count.

Things worth not re-deciding:

- **It never writes card content.** A term's translation, example and description are written by the
  lookup model against a prompt that knows about CEFR level, register and isomorphism. A
  general-purpose translator knows none of that, and letting it near the catalogue would fill it
  with plausible rows nobody reviewed.
- **A 2-second timeout and no retry.** The hint's value is landing while the learner still looks at
  the field; a second attempt could only arrive later than the first. Slow is treated as absent.
- **Nothing throws.** No key → `feature_disabled`; no budget → `limit_reached`; dead vendor → an
  empty line. Search and the lookup are untouched by all three.
- **The budget stops at 95%,** not 100% (`TranslationMonthlyBudget`): the last calls of the month
  should fail as a decision we made, not as a vendor 456 in the middle of somebody's search. The
  month's spend is a `SUM(characters)` over cache rows, so it cannot drift from what was bought.
- **A cached word is served even past the budget** — it costs nothing, and withholding it would
  enforce a limit on money nobody is spending.

`search_lookup` is on the Observability purpose whitelist — added in the SAME change as the adapter,
because the two migrations before it (`translation_repair`, `playground`) are the same hole found
after a month of untracked spend. `instant_translation` is there for the same reason and one better:
those calls cost no dollars at all, so the request log is the ONLY place the app's approach to its
character ceiling is visible.

## Pipeline

```
POST /generations → RequestCollectionGeneration (quota check, insert pending) → 202 {id}
                  → dispatch GenerateCollectionJob
GenerateCollectionJob → ProcessGeneration:
     markRunning (IDEMPOTENT — a retry re-enters its own run) → CollectionGeneratorPort::generate
       (slow, OUTSIDE any tx) — ask ceil(size×1.3)
     → DraftValidator (CEFR/dedup, cap at MAX_ITEMS) → trimToRequested (down to `size`)
     → if still short: ONE top-up with an avoid list → merge+dedup+cap (tokens/cost SUMMED)
     → [tx: CreateGeneratedCollection + ImportTerm×N + AddTerm]
     → markSucceeded (collection_id, tokens, cost, delivered_count)
GET /generations/{id} → the client polls until succeeded|failed; reads `requested`/`delivered`
```

The console command `php artisan generation:make {user} {prompt}` runs the same
`ProcessGeneration` **synchronously** and prints the result — no queue, handy from the terminal.

`markRunning` is idempotent from `running` on purpose. The job has `tries = 3`, so an attempt that
marks the request running and then dies of something else is followed by a retry that arrives at a
request already running. While that threw, the transition error REPLACED the real cause and was what
`failed()` recorded — three of the owner's generations died on 25.08 reporting «Cannot move a
generation from running to running», and why they really failed is not in anything we stored. A
terminal request is still refused: succeeded and failed are final, the quota is settled, and
re-running one would be a second charge for finished work.

## The port

`Application/Port/CollectionGeneratorPort` is the only seam to the LLM.
`Infrastructure/Adapter/OpenAiCollectionGenerator` (structured outputs, strict JSON schema)
is the real one; `FakeCollectionGenerator` is deterministic and bound when
`GENERATION_DRIVER=fake` (tests, and local dev without a key). Prompts are versioned files
(`Infrastructure/Prompt/generate_collection.v1.md`), never inline; the user's prompt is
passed as delimited data, never as instructions.

## Prompt versions — v10 is composed, not a file

v1–v9 are single frozen files (`Infrastructure/Prompt/generate_collection.vN.md`). **v10 is a
DIRECTORY of sections** (`Infrastructure/Prompt/v10/*.md`) assembled by `PromptLibrary` into three
SHAPES:

| Shape | Product | Used by |
|---|---|---|
| `terms` | topic → list of terms with translations | what production generation does (bake-off track А) |
| `enrich` | existing terms → full content (translation, example, options) | the enrichment станок (track Б) |
| `full` | topic → finished collection in ONE call | the one-shot experiment (track В) |

Composed because the rules that matter most — the translation-key isomorphism, **both waves** — are
identical in all three, and three files would mean three copies of the rule the last two content
sweeps were about. A test asserts the section is byte-identical across shapes. `RenderedPrompt`
carries a sha256 of the rendered text, so a section edited without a version bump is visible.

What v10 adds to v9: wave 2 of the isomorphism rule (a translation must not ADD what the term never
said — «Я *хорошо* лажу со своей командой»), a **mandatory** example (no example → no card on rung 3
→ the term drops out of the course), no two items sharing a `translation`, and a self-check that
demands the last item of a long answer be read as carefully as the first.

**Production still generates on v9** (`RequestCollectionGenerationHandler::PROMPT_VERSION`).
Switching it is a decision from the bake-off numbers, not a side effect.

## Content provenance

Generated content carries `prompt_version` + `generation_model` **per row** (`terms`,
`term_translations`, `term_examples`; `generation_model` beside the existing `generator_version` on
`term_accepted_variants` / `example_distractors`). Per row, not per request, because terms are
deduplicated and a later prompt's translation can hang off an earlier prompt's term. Rows written
before the column exist carry the sentinel `legacy`; a NULL afterwards means a writer created
content without stamping it, which is a bug the column can then find by itself.

`example_translations` (A-1) carries no stamp of its own: a gloss is written by the same call as the
sentence it sits beside, so the example row's stamp answers for both. That stops being true the day
a gloss is generated on its own — a back-translation into a second support language — and the
columns go on that table then, deliberately, rather than being added now for a caller that does not
exist.

## Providers — one seam, three vendors

`Application/Port/ContentModelPort` = one vendor, one structured JSON answer. Narrower than
`CollectionGeneratorPort` on purpose: that port can express exactly one product, and the three
shapes above need three.

| Provider | Adapter | Key | Model env |
|---|---|---|---|
| OpenAI | `OpenAiCompatibleContentModel` | `OPENAI_API_KEY` | `OPENAI_COMPARE_MODEL` |
| xAI (Grok) | the same adapter, different base url — xAI implements OpenAI's request shape | `GROK_API_KEY` | `XAI_GENERATE_MODEL` |
| Anthropic | `AnthropicContentModel` (`x-api-key`, top-level `system`, `output_config.format`) | `ANTHROPIC_API_KEY` | `ANTHROPIC_GENERATE_MODEL` |
| Google | `GeminiContentModel` (`x-goog-api-key` header, `systemInstruction`, `generationConfig.responseSchema`) | `GEMINI_API_KEY` | `GEMINI_GENERATE_MODEL` |

**`OPENAI_COMPARE_MODEL` is not `OPENAI_GENERATE_MODEL`.** The second is what production generation
runs on; the first is what the comparison runs on. One variable for both would mean a bake-off
silently repoints live generation. Unset, the comparison falls back to the production model.

**Gemini's schema is not JSON Schema.** `responseSchema` is an OpenAPI-3.0 subset that *rejects*
`additionalProperties` — which OpenAI's strict mode *requires*. `GeminiContentModel` translates on
the way out. This is the only place where "every provider gets the same schema" is not literally
true; the same constraint is expressed in each vendor's dialect.

`ConfiguredContentModelCatalog` reports availability: **no key is not an error**, it is a provider
that does not run, named in the report with the env var that would fix it. Retries escalate
(4s/8s/12s) and only on statuses that can change by themselves — a 429 is a per-minute token ceiling
and clears; a 403 (no credits) never will.

## The bake-off (`php artisan generation:bakeoff`)

Compares providers on identical work, writes only to the sandbox (`bakeoff_runs` / `bakeoff_calls` /
`bakeoff_candidates`), and exports one readable comparison file. Three tracks, because A and B are
two halves of today's pipeline and can have different winners; C is the one-shot experiment.
`ContentChecks` (Domain) judges every item by the detectors the rest of the app already uses —
`LanguagePurity` and, through Vocabulary's Application, `AddresseeIsomorphism`. `--dry` prints the
plan and spends nothing; `--pace=` spaces calls under an org token-per-minute cap.

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
  what actually landed (client shows "13 из 15"), never a failure. **An over-delivery IS trimmed**:
  `GenerationPipeline::trimToRequested` cuts the batch back to exactly `size` (owner decision
  2026-08-23, QA-OBS-9, `61be58a`, reversing the 2026-08-18 «size is approximate»). `MAX_ITEMS` (25)
  stays as the hard ceiling above that.
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
