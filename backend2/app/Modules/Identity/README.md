# Identity

**Owns:** users, auth tokens, Google sign-in, user settings (profile)

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

Controllers depend only on these ports and on DTOs (`UserView`, `AuthResult`, …); responses
are built from DTOs via Resources, never from models.

## Sign-in flow

Native Google Sign-In on the device yields an ID token → `POST /api/v1/auth/google`
→ verify audience against accepted client ids → upsert user (keyed by Google `sub`) →
ensure a `Profile` exists → issue a per-device Sanctum token. Idempotent across logins.

## Endpoints

- `POST /api/v1/auth/google` — `{id_token, device_name?}` → `{token, user}`. Invalid token → 422.
- `GET /api/v1/auth/me` — the authenticated user (+ profile).
- `POST /api/v1/auth/logout` — revokes the current token (204).
- `PUT /api/v1/profile` — partial update of learning preferences.

## Schema notes

`users.id` is an **uppercase ULID** (`newUniqueId()` uses Shared `Ulid`) so it matches the
`UserId` value object every other module keys on. `personal_access_tokens.tokenable_id`
uses `ulidMorphs`. Config: `services.google.client_ids` from `GOOGLE_IOS_CLIENT_ID` /
`GOOGLE_WEB_CLIENT_ID`.

**Not ported from `../backend`:** starter-content seeding on first sign-in (backend2 has no
seed content yet — revisit once Generation/Collections seeds exist).
