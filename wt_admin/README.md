# wt_admin — WordTrainer admin console

Read-only data console for the app (users, collections, terms, spend, logs, a user's
SRS plan for a chosen day, review feed, dialogs). One mutation: the Premium toggle.
Built in the app's **paper/ink** design system — light theme only.

Stack: **Vue 3 + Vite + TypeScript + Pinia + vue-router**, no heavy UI kit — a small
in-house component layer in `src/components/`. Fonts (Literata + Inter) are bundled
(offline). Icons: `lucide-vue-next`.

## Run locally

```bash
cd wt_admin
npm install
npm run dev            # http://localhost:5174 — standalone on mock data
```

By default (no `VITE_API_BASE`) it serves **built-in mock data** by the contract in
`CONTRACT-ASSUMPTIONS.md`, so it runs before the backend admin API exists. Point it at
a real backend:

```bash
VITE_API_BASE=http://localhost:8001/admin/api npm run dev
```

## Checks

```bash
npm run type-check     # vue-tsc, strict
npm run lint           # eslint (vue3-essential + ts), 0 warnings
npm run test           # vitest — utils, mock contract, key components/pages
npm run build          # type-check + production build
```

## Docker

Multi-stage (`node` build → `nginx`). nginx **reverse-proxies `/admin/api` → the backend**
(`BACKEND_ORIGIN`), so the SPA calls the API **same-origin — no CORS**, and the backend's
`config/cors.php` is never involved. A per-container entrypoint writes `/config.js` from env
at start, so behaviour changes **without a rebuild**.

```bash
docker build -t wt-admin .
# BACKEND_ORIGIN: where nginx proxies /admin/api. For a plain `docker run` against the
# backend published on the host :8001, use host.docker.internal (the default):
docker run -p 5175:80 -e BACKEND_ORIGIN=http://host.docker.internal:8001 wt-admin
# → open http://localhost:5175, log in with an admin account.
```

Wired into `../backend2/docker-compose.yml` as service **`admin`** (container `wt_admin`,
host port `5175`). On the compose network it proxies to the internal service, so nothing
about the browser's origin matters:

```bash
cd ../backend2
docker compose up -d --build admin        # BACKEND_ORIGIN defaults to http://wt_app:8000
```

Env: `USE_MOCKS=true` runs standalone on demo data (ignores the backend). `API_BASE` can
override the SPA's base URL, but the default empty value (same-origin `/admin/api`, proxied)
is what avoids CORS — prefer it.

**Admin login is a separate `admins` guard, not an app user.** Create one:

```bash
docker compose exec app php artisan admin:create you@example.com --name="You"
```

## Contract

The authoritative contract is `backend2/openapi/openapi-admin.yaml`, authored by the
**backend session**. It exists, and this app is **SYNCED** to it: `src/api/types.ts` is the
camelCased mirror, `src/api/mapping.ts` is the snake_case ↔ camelCase boundary, and the mock
adapter (`src/api/mock/`) emits the same shapes for offline demo. `CONTRACT-ASSUMPTIONS.md`
records what was assumed and how it was reconciled. **When the BE contract moves, diff it against
that doc and report divergences — do not silently reshape the frontend.**

## Layout

```
src/
  api/          # typed facade (index.ts) → http.ts (axios) or mock/ by config
  components/   # paper design-system components (PaperCard, DataTable, Badge, …)
  composables/  # useAsync, usePaginated
  layouts/      # AdminLayout (sidebar shell)
  stores/       # auth (Pinia)
  styles/       # tokens.css (paper/ink vars), base.css, fonts.css
  utils/        # format (relative dates, money 4dp), labels, languages
  views/        # Dashboard, Users(+user/*tabs), Collections, Terms, Logs, Login
```

## v1 scope (per brief)

Included: server-side pagination, empty/error states in paper style, relative dates
with exact tooltip, money at 4 decimals, the Premium mutation with confirmation.
Language codes are rendered through `src/utils/languages.ts` — the console's copy of the
one-per-repository language catalogue (endonym, names, flag), shared in shape with
`backend2/.../LanguageCatalog.php` and `mobile/lib/l10n/language_endonyms.dart`.
**Not** in v1: any other data mutation, charting libraries (figures are cards),
realtime updates.
