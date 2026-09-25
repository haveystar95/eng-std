# Identity

**Owns:** users, auth tokens, Google sign-in, user settings (profile), devices (push addresses
and visits — PLAN-UI-3), the rights to the paid plan (`entitlements` — ACC-1 §2), the account's deletion and its count
(`account_deletions` — ACC-1 §1)

A deliberately **thin, Laravel-native** module: the user is a Sanctum-backed Eloquent
model, not a domain aggregate — auth has no invariants worth an anti-corruption layer.

## Layering (thin, but deptrac-clean)

Controllers may not touch Eloquent/Infrastructure, so the parts that do (Eloquent `User`/
`Profile`, Sanctum, the Google library) live in `Infrastructure` behind **Application ports**:

| Port (`Application/Port`) | Impl (`Infrastructure`) | Used by |
|---|---|---|
| `GoogleTokenVerifier` | `Adapter/GoogleAuthTokenVerifier` (`google/auth`) | sign-in |
| `GoogleSignIn` | `Auth/SanctumGoogleSignIn` | `POST /auth/google` |
| `UserReader` | `Eloquent/EloquentUserReader` | `GET /auth/me` |
| `ProfileUpdater` | `Eloquent/EloquentProfileUpdater` | `PUT /profile` |
| `SignOut` | `Auth/SanctumSignOut` | `POST /auth/logout` |
| `PushTokenStore` | `Eloquent/EloquentPushTokenStore` | `PUT`/`DELETE /devices/push-token`; `GetPushTokens` |
| `VisitLog` | `Eloquent/EloquentVisitLog` | `POST /devices/visit`; `GetUsualVisitTime` |
| `EntitlementStore` | `Eloquent/EloquentEntitlementStore` | `GrantAccess`, `RevokeAccess`, `GetAccess`, `GetEntitlements` |
| `AccountEraser` | `Eloquent/CrossModuleAccountEraser` | `DELETE /auth/me` |

Controllers depend only on these ports and on DTOs (`UserView`, `AuthResult`, …); responses
are built from DTOs via Resources, never from models.

## Access to the paid plan (ACC-1 §2)

`entitlements` — one row per learner and source (`admin` | `promo` | `apple` | `google`): `product` month | year | lifetime,
`status` active | expired | grace, `starts_at`, `expires_at` (null — no end). The one rule (`Domain/Service/AccessRule`): a
right is in force while its status is active or grace and its end is ahead or none; the learner's access is `premium`
while any right is, named by the one that lasts longest (`Domain/ValueObject/Access`). Queries `GetAccess(userId)` →
`AccessView {plan, expires_at, source}` (on `GET /auth/me` as `access`; the Plan module's paywall asks it through
`LearnerAccess`) and `GetEntitlements(userId)` → `list<EntitlementView>` (the admin's page). Commands `GrantAccess`
(the source's row rewritten, from now, for the product's term or `until`) and `RevokeAccess` (every right in force
expires now). Console: `access:grant {user} {product} {--until=}`, `access:revoke {user}` (a learner by id or email).
No purchase door — the store sync of PAY-1 writes its own rows. `profiles.tier` (`UserTierReader`/`Writer`) is the older,
separate premium switch of the store, the generation limit and the practice; it is not read by the access.

## Account deletion (B3, ACC-1 §1)

`CrossModuleAccountEraser` — one transaction begun by locking the user's row (a second deletion waits and finds it gone:
`AccountNotFound`, 404 `account_not_found`): every module's own eraser (plans with their files after the commit,
collections, the pool, generations and practice, the request log and the admin audit unlinked, authored terms
anonymised), one row of `account_deletions` (HMAC-SHA256 of the id under the application key, `plans_count`,
`deleted_at` — a count, not a person), the tokens revoked, the user row deleted — the profile, push addresses, visits,
rights and trainer overrides by FK cascade. `DELETE /auth/me` then forgets the request's user, so the request log's row of
the deletion names nobody.

## Devices (PLAN-UI-3)

Commands `RegisterPushToken`, `RemovePushToken` (owner-scoped from the endpoint; unscoped when APNs
says the token is dead), `RecordVisit`; queries `GetPushTokens(userId)` → `list<PushTokenView>` and
`GetUsualVisitTime(userId)` → `UsualVisitTimeView`. These two queries and `RemovePushToken` are the
public surface the Plan module's notifications read through (`IdentityLearnerDevices`,
`IdentityLearnerHabits`).

Pure rules (`Domain/Service`): `VisitThrottle` — a visit within 30 minutes of the last RECORDED one
writes no row; `UsualVisitTime` — the last 7 visits read in `profiles.timezone`, the median, rounded
down to the quarter hour, 19:00 with no visits; a visit before 04:00 counts as the tail of the
previous evening for the median (so 23:50 / 00:10 / 00:20 is «around midnight», not a morning).

## Sign-in flow

Native Google Sign-In on the device yields an ID token → `POST /api/v1/auth/google`
→ verify audience against accepted client ids → upsert user (keyed by Google `sub`) →
ensure a `Profile` exists → issue a per-device Sanctum token. Idempotent across logins.

## Endpoints

- `POST /api/v1/auth/google` — `{id_token, device_name?}` → `{token, user}`. Invalid token → 422.
- `GET /api/v1/auth/me` — the authenticated user (+ profile, the generation allowance and — ACC-1 — `access`).
- `DELETE /api/v1/auth/me` — the account and everything of it (204); a repeat that got past the token check before the
  first deletion committed — 404 `account_not_found`; with the same (revoked) token — 401.
- `POST /api/v1/auth/logout` — revokes the current token (204).
- `PUT /api/v1/profile` — partial update of learning preferences.
- `PUT /api/v1/devices/push-token` — `{platform: ios, token, locale?, timezone?}` → 204; upsert by
  (platform, token), a token seen under another account moves to the caller.
- `DELETE /api/v1/devices/push-token` — `{platform, token}` → 204; only the caller's own row.
- `POST /api/v1/devices/visit` — `{timezone?}` → 204; throttled to one row per 30 minutes.

## Schema notes

`users.id` is an **uppercase ULID** (`newUniqueId()` uses Shared `Ulid`) so it matches the
`UserId` value object every other module keys on.
`device_push_tokens` (unique `(platform, token)`, index `user_id`) and `user_visits` (append-only,
index `(user_id, visited_at DESC)`) both cascade with the user row, like `profiles`. `personal_access_tokens.tokenable_id`
uses `ulidMorphs`. Config: `services.google.client_ids` from `GOOGLE_IOS_CLIENT_ID` /
`GOOGLE_WEB_CLIENT_ID`.

**Not ported from `../backend`:** starter-content seeding on first sign-in (backend2 has no
seed content yet — revisit once Generation/Collections seeds exist).
