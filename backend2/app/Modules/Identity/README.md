# Identity

**Owns:** users, auth tokens, Google sign-in, user settings (profile), devices (push addresses
and visits — PLAN-UI-3)

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

Controllers depend only on these ports and on DTOs (`UserView`, `AuthResult`, …); responses
are built from DTOs via Resources, never from models.

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
- `GET /api/v1/auth/me` — the authenticated user (+ profile).
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
