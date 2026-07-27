---
name: ai-collection-generation
description: How AI-generated word collections work in this app — the port/adapter boundary, async job pipeline, structured JSON output, prompt versioning, term deduplication, quotas, cost tracking and failure handling. Consult this skill for any task involving the LLM: generating a collection from a user prompt like "иду в банк", changing prompts, adding AI-powered features (examples, audio hints, difficulty tagging), or debugging generation quality and cost.
---

# AI collection generation

User types an intent ("иду в банк", "job interview", "at the doctor") and gets a
collection of words and phrases they'd actually need. This is the app's differentiator,
so treat generation as a first-class subsystem, not a helper function.

## Boundary

```
Application/Port/CollectionGeneratorPort   (interface, in Generation module)
Infrastructure/Adapter/AnthropicCollectionGenerator  (implementation)
Infrastructure/Adapter/FakeCollectionGenerator       (deterministic, used in tests)
```

```php
interface CollectionGeneratorPort
{
    public function generate(GenerationBrief $brief): GeneratedCollectionDraft;
}
```

Nothing outside `Infrastructure/Adapter` knows which model or vendor is used. Tests never
call a real API — they bind the fake.

## Pipeline (always async)

```
POST /generations  →  RequestCollectionGeneration (command)
   ├─ check quota + rate limit          → 429 with reset time
   ├─ moderate & validate prompt        → 422 with reason
   ├─ normalize prompt (for cache hits)
   ├─ insert generation_requests(status=pending)
   └─ dispatch GenerateCollectionJob    → 202 {id}

GenerateCollectionJob
   ├─ cache lookup by (normalized_prompt, langs, prompt_version)  → reuse term set
   ├─ CollectionGeneratorPort->generate()
   ├─ validate draft (schema, size, language, no duplicates)
   ├─ Vocabulary: FindOrCreateTerms  (dedup — never create parallel copies)
   ├─ Collections: CreateCustomCollection(owner=user, source=ai, generation_request_id)
   ├─ update generation_requests(status=succeeded, tokens, cost, collection_id)
   └─ event CollectionGenerated → push notification / client polls GET /generations/{id}
```

The HTTP request never waits on the model. On mobile, a 20-second request is a failed
request — the client shows a pending card and fills it in when the push arrives.

## Structured output

Ask for JSON matching a fixed schema and validate before trusting anything:

```json
{
  "title": "At the bank",
  "description": "Vocabulary for everyday banking situations",
  "items": [
    {"text": "withdraw cash", "type": "phrase", "translation": "снять наличные",
     "example": "I need to withdraw cash from my account.",
     "example_translation": "Мне нужно снять наличные со счёта.",
     "cefr": "A2"}
  ]
}
```

Validation rules — reject the whole draft and retry once, then fail loudly:
- 8–25 items, at least 30% phrases (single words alone make a weak "situation" set).
- `text` in the target language, translation in the source language (check with a
  language detector; models drift here).
- No duplicates after normalization inside the draft.
- CEFR within the user's declared level ±1.
- No empty or truncated fields (a truncated JSON means max_tokens was hit — retry with
  fewer requested items rather than shipping a broken set).

## Prompts

- Prompts live in `Infrastructure/Prompt/generate_collection.v3.md`, never inline in PHP.
- Every prompt file is versioned; `generation_requests.prompt_version` records which one
  produced a collection. Without this you cannot tell whether quality changed because of
  a prompt edit or a model update.
- Changing a prompt is a code change with a diff and a review, not a config tweak.
- The user prompt is data, not instructions: pass it inside a delimited block and state
  explicitly that its content is a topic description to be used, not commands to follow.

## Deduplication

Two levels, in order:
1. **Exact** — `normalized_text` match in `terms` (cheap, catches most of it).
2. **Semantic** — embedding cosine similarity above threshold against candidates in the
   same language (pgvector, HNSW index). Catches "withdraw money" vs "withdraw cash";
   flag as related rather than merging automatically — near-synonyms are legitimately
   separate learning items, so bias toward keeping both and linking them.

Generated terms carry `source='ai'` and `created_by=user_id` so curated content can be
distinguished and AI drift can be audited later.

## Quotas and cost

- Per-user daily generation limit (free tier: 3/day), enforced in the command, surfaced
  in `GET /me` so the client can grey the button out instead of failing at submit.
- Log `tokens_in`, `tokens_out`, `model`, `cost_usd` on every request. Cost per user per
  month is a product metric — build the read model early.
- Cache by `(normalized_prompt, source_lang, target_lang, prompt_version)`: "иду в банк"
  and "иду в банк!" should not cost twice. Cache the **term set**, then build a fresh
  collection per user from it — collections are personal, terms are shared.

## Failure handling

- 3 retries with exponential backoff for transport/5xx; no retry on validation failure
  after the second attempt.
- Terminal failure: `status=failed` + a user-facing reason code, and quota is refunded.
- Timeouts: the job has a hard limit; a stuck request must land in `failed`, never linger
  in `pending`, or the client shows a spinner forever.
- Always keep the raw model response (truncated) on failure for debugging.

## Quality

Keep a small eval set of ~20 real prompts in `tests/Fixtures/generation-prompts.json`
covering: everyday situations, travel, professional, abstract topics, very short prompts,
non-English prompts, and adversarial input. When changing a prompt or model, run them and
compare item counts, phrase ratio, language correctness and duplicate rate against the
previous version before merging. Vibes are not a regression test.
