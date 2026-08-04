# eng-std — project master guide

Personal English-learning app (single user, Denis; no App Store planned). Flutter/iOS
client + Laravel API. This file auto-loads for any session in this repo — it's the map.
Detailed rules live in the per-directory files linked below.

## Layout

| Dir | What it is | Status |
|---|---|---|
| `mobile/` | Flutter/iOS app (the product). Dark UI, Riverpod. | **Live on the phone.** See `mobile/CLAUDE.md`. |
| `backend/` | **MVP API, currently LIVE** — the app talks to this. Flat Laravel (Http/Models/Services/Actions/Policies/Resources), SQLite, FSRS, Google auth, AI. | Working. Keep running; change only if asked. |
| `backend2/` | **Paradigm rewrite in progress** — modular monolith + DDD. Postgres/pgvector/Redis/Horizon. Not yet wired to the app. | See `backend2/CLAUDE.md` + `backend2/docs/ROADMAP.md`. |
| `docs/` | `API_CONTRACT.md` (old backend ↔ Flutter contract). | — |

There are **two backends on purpose**: the old `backend/` serves the app today; `backend2/`
is the clean rebuild. The app is cut over to `backend2` only in ROADMAP Phase 4.

## How to continue after a context reset

1. This file + `backend2/CLAUDE.md` + `mobile/CLAUDE.md` auto-load. Read them.
2. Read the memory notes and `backend2/docs/ROADMAP.md` (has phase-by-phase status).
3. Continue at the first unchecked ROADMAP item (currently: **Learning** module in backend2).
4. Old backend + mobile knowledge (gotchas, run commands) is in `mobile/CLAUDE.md` and the
   "Old backend" section below.

## Process tooling (`.claude/`) — what exists and when to use it

Our working rituals are encoded in `.claude/` so they don't depend on a session remembering them.

| Tool | Kind | When |
|---|---|---|
| commit gate | PreToolUse hook (`.claude/hooks/pre-commit-gate.sh` + `settings.json`) | **Automatic** on `git commit`: runs `composer check` (arch+stan+test, Docker) for backend2 changes and `flutter analyze` for mobile changes; red **blocks** the commit. Bypass a WIP commit with `SKIP_GATES=1` (it warns). Don't run the gates by hand before committing — the hook owns them. |
| `/handoff` | command | Rewrite `backend2/docs/session-handoff.md` as a fresh snapshot (done-with-hashes, device-verified-vs-code-only table, non-negotiable decisions, what's next, known limits). |
| `/audit <area>` | command | Read-only Stage-1 audit of a module: code vs skills vs invariants, ending with questions, then **stops** for confirmation. No edits. |
| `/close-task` | command | Definition-of-done checklist with ✅/❌ (gates, `migrate:fresh` on the test DB, OpenAPI, findings-doc, handoff, device-unverified, findings→ROADMAP). |
| `invariant-reviewer` | subagent | **Manual**, run before `/close-task`: checks the diff against the project invariants (Domain purity, progress on (user,term), append-only logs, client_seq order, one «усвоено», cross-module via Application, client reads-from-DB + cursor-in-DB). Reports violations or CLEAN — invariants only, no style. |

New process rules change **here** (a skill/command/hook/agent file), never silently in one commit.
Note: a newly added command/subagent or an edited `settings.json` may need a fresh Claude Code
session to register.

## Old backend (`backend/`) — the live API

- Laravel 13, PHP 8.4, **SQLite**, all in Docker: `engstd_app` (:8000), `engstd_queue`, `engstd_ngrok`.
- Run: `cd backend && docker compose up -d`. Artisan: `docker compose exec app php artisan …`.
- **Public URL:** `https://greedily-thermos-finer.ngrok-free.dev` (ngrok static domain, stable). If it 404s with `ERR_NGROK_334`, a stray host ngrok grabbed the domain — `pkill -f "ngrok http"` then `docker compose up -d ngrok`.
- **Auth:** Google sign-in → Sanctum token. `GOOGLE_IOS_CLIENT_ID` in `.env`; `../credentials.plist` has the OAuth client (bundle `com.denis.engstd`).
- **AI:** pluggable `AI_PROVIDER` = `openai` (current) | `ollama` | `claude`, behind `App\Services\Ai\AiProvider`. OpenAI split models: `OPENAI_GENERATE_MODEL=gpt-4o`, `OPENAI_CHECK_MODEL=gpt-4o-mini`. `OPENAI_API_KEY` set (user's). Ollama = native on host (Metal), reached at `host.docker.internal:11434`, model `qwen2.5:7b`. Claude key present but **org has no credits** (Ukrainian card can't top up Anthropic). Generation is **scenario-aware**: `topic` may be a Russian situation ("иду открывать счёт в банке") → returns a phrase/sentence-heavy mix, filtered to requested CEFR levels.
- **Data model:** words deduplicated per user (`words` unique on `user_id`+`term_key`), collections↔words many-to-many via `collection_word` pivot, `review_states` keyed `(user, word)` shared across collections, progress via FSRS **stability** (learned ≥ 4, mastered ≥ 21). SRS impl: `app/Services/Fsrs.php`.
- **Gotcha:** the queue worker caches Job/class code in memory — after editing `app/Jobs/*` or AI prompt/provider code, `docker compose restart queue`. After `.env` edits, restart `queue` (app reads env per request).
- Endpoints: `POST /auth/google`, `GET /auth/me`, profile, collections CRUD+`generate`, words CRUD, `reviews/due` + `reviews/{word}/answer`, `stats`, `ai/check`, `ai/jobs/{id}`. Contract: `docs/API_CONTRACT.md`.

## Host tooling (already installed)

Flutter (brew), Composer, ngrok (authtoken set), Docker Desktop, TablePlus, **Xcode 27 beta 3**
(required — host is macOS 27 beta; App Store Xcode 26.x is incompatible). User prefers running
backend services **in Docker**, not on the host.

## DB access (GUI)

- Old backend: SQLite file `backend/database/database.sqlite` — open directly in TablePlus.
- backend2: Postgres — TablePlus → `localhost:5433`, db/user/pass `wordtrainer`/`wordtrainer`/`secret`.
