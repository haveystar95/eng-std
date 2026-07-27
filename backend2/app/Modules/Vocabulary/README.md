# Vocabulary

**Owns:** terms (word or phrase), translations, examples, audio, dedup

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http).

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/` for the rules.
Boundaries enforced by `deptrac.yaml`.
