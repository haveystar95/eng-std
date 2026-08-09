# Admin module

Back-office admin panel API. A read-mostly, cross-cutting reporting surface: it lets the operator
(Ден) see all app data — users, collections, terms, spend, logs, a user's SRS state and their
simulated plan for a day — and perform the one v1 mutation (set a user's tier). Nothing here is
reachable by app users.

Tables: `admins`, `admin_audit_log`. It **reads** other modules' read tables as projections but
owns none of them.

## Model style

`thin` (Laravel-native), like Identity. Auth is the framework's job (Sanctum), and the rest is
read projections + one mutation — there are no domain invariants of its own to protect, so there is
no Domain layer. The invariants that matter (mastery, scheduling) are asked of the modules that own
them, never re-implemented here.

## Auth

Separate Sanctum guard `admin` (config/auth.php) whose provider is the `admins` table. A token minted
for an app `User` fails this guard's provider check, so app users cannot reach `/admin/api/*` with
their own token. `admin:create {email} {--name=}` seeds an operator.

## Public surface

Admin is a leaf: no other module calls it. Its HTTP surface is `/admin/api/*`, described in the
SEPARATE contract `openapi/openapi-admin.yaml` (the app's `openapi.yaml` is untouched). CORS is
scoped to `admin/api/*` for the `ADMIN_ORIGIN` browser origin (config/cors.php).

## Depends on (cross-module, via Application only)

| Module | How | Why |
|---|---|---|
| `Learning` | Query `GetDayPlan` | the day-plan simulator — the real GetDueTerms + ExerciseSelector, dry-run, no writes |
| `Learning` | Query `GetUserStats` | mastered/learned/due/streak (Mastery is the single source of «усвоено») |
| `Identity` | Port `UserTierWriter` / `UserTierReader` | the tier mutation goes through the tier's owner (same path as `practice:grant-premium`) |

## Ports (outbound interfaces)

Reader ports (implemented by `Eloquent*` projections in Infrastructure): `AdminMetricsReader`,
`AdminCostReader`, `AdminUserReader`, `AdminReviewReader`, `AdminCollectionReader`, `AdminTermReader`,
`AdminRequestLogReader`, `AdminDialogReader`, `AdminGenerationReader`. Auth/audit ports: `AdminLogin`,
`AdminRegistrar`, `AdminReader`, `AdminSignOut`, `AdminAuditRecorder`.

## Notes / deliberate decisions

- **Cross-module reads.** The read projections query other modules' read tables directly (single
  table each; cross-entity joins done in PHP, never across module tables). This is a deliberate
  reporting-layer choice, like Observability — Admin imports no other module's classes (deptrac stays
  green), and it keeps six modules from growing admin-only query surfaces. The two reads with real
  domain meaning are NOT reconstructed here: the day plan and «усвоено»/stats go through Learning's
  Application; the tier write goes through Identity's.
- **The day-plan simulator writes nothing** — no StudySession, no progress, no reviews (covered by a
  test that asserts the row counts are unchanged).
- **Audit trail.** Every mutation appends to `admin_audit_log` in the same command as the write. v1
  has one mutation (tier); future mutations go through the same table.
- `term_enrichments` has no `user_id`, so enrichment spend appears in the fleet dashboard but not in
  a per-user breakdown.
