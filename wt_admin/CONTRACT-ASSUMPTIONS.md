# wt_admin — contract sync with `openapi-admin.yaml`

**Status: SYNCED to the real BE contract.** The frontend is now built against
`backend2/openapi/openapi-admin.yaml` (authored by the BE session), verified against the
live API at `http://localhost:8001/admin/api`. FE DTOs (`src/api/types.ts`) are the
camelCased mirror of that file; a thin generic boundary (`src/api/mapping.ts`) converts
snake_case ↔ camelCase and the `{data, meta:{total,page,per_page}}` envelope (deriving
`totalPages`). The mock adapter emits the same camelCased shapes for offline demo.

> Earlier this doc held FE *assumptions* (the contract didn't exist yet). That guesswork
> has been replaced by the real contract. What remains below is the list of **residual
> divergences to raise with the BE session** — places where the contract can't back a
> planned admin feature, or is worth a small addition.

## Endpoint map (FE ↔ BE), as implemented

| FE call | BE endpoint |
|---|---|
| `api.login` | `POST /login` (`{email, password, device_name}` → `{token, admin}`) |
| `api.me` / `api.logout` | `GET /me` / `POST /logout` |
| `api.dashboard` | `GET /dashboard` |
| `api.listUsers` / `api.getUser` | `GET /users` / `GET /users/{id}` |
| `api.getUserPlan` | `GET /users/{id}/plan?date=` |
| `api.getUserReviews` | `GET /users/{id}/reviews?from=&to=` |
| `api.setTier` | `POST /users/{id}/tier` (`{tier: free\|premium}`) — the one v1 mutation |
| `api.listCollections` / `api.getCollection` | `GET /collections?type=&search=` / `GET /collections/{id}` |
| `api.listTerms` / `api.getTerm` | `GET /terms?search=` / `GET /terms/{id}` |
| `api.listLogs` | `GET /request-logs?user_id=&status=&path=` |
| `api.listDialogs` / `api.getDialog` | `GET /practice-dialogs?user_id=` / `GET /practice-dialogs/{id}` |
| `api.listGenerations` | `GET /generations?user_id=&status=` |

Per-user **Collections** tab is served from the embedded `UserDetail.collections` (no
separate endpoint needed). Per-user **Logs / Dialogs / Generations** tabs reuse the global
list endpoints with `user_id=`.

## Residual divergences — for the BE session (report, don't silently patch)

1. **Request logs expose no bodies and no detail endpoint.** `RequestLog` is metadata only
   (method/path/status/duration/user_id); there is no `/request-logs/{id}` and no
   `request_body` / `response_body` / headers in the row. The brief asked for
   "раскрытие запрос/ответ" — the admin can't inspect payloads. The data exists in the
   `api_request_logs` table (already redacted). **Proposal: add `GET /request-logs/{id}`
   returning the redacted bodies + headers.** Until then the Logs UI shows metadata only.

2. **Logs/collections/dialogs reference users & collections by id only** (`user_id`,
   `owner_id`, `collection_id`) — no email/title for display. FE shows a shortened id that
   links to the entity. A denormalized `user_email` / `collection_title` on those rows would
   let the tables read naturally. (Minor.)

3. **Unauthenticated `GET /admin/api/*` returns 500, not 401** (observed on `/dashboard`).
   Looks like the `auth:admin` guard resolving a missing `login` route. BE zone — flagged.
   Doesn't affect authenticated use.

4. **`/request-logs` has no `direction` filter** although `direction` is a row field. The
   global Logs page dropped the direction filter to match. (Minor — add the param if wanted.)

5. **Dashboard totals** expose `users/collections/terms/reviews_today/reviews_7d` — no
   dialogs or reviews-all-time total. The overview cards reflect exactly what's provided.

## Auth / setup notes

- Admin auth is a **separate `admins` guard** (Sanctum), not app users. Create an admin with
  `docker compose exec app php artisan admin:create <email> --name="…"` (password prompted).
- A demo admin was created during verification: `admin@wordtrainer.local`.
- CORS: the SPA always calls the API **same-origin** and never triggers CORS. In `vite dev`
  the `/admin/api` proxy (`vite.config.ts`) forwards to `:8001`; in the nginx container the
  server reverse-proxies `/admin/api` → `BACKEND_ORIGIN` (`docker-entrypoint`/`nginx.conf`).
  So the backend's `config/cors.php` is never involved — no BE change needed. Verified
  end-to-end through both the dev proxy and the built container.
