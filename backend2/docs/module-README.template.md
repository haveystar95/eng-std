# <Context> module

> Copy this file to `app/Modules/<Context>/README.md` and fill it in when creating a
> module. Keep it under a page — it exists so that a reader (human or agent) knows what
> this module is allowed to do without reading all of its code.

## Owns

One or two sentences: which concepts, which tables, which part of the product.

Tables: `table_a`, `table_b`

## Model style

`rich` or `thin`, and why.

- **rich** — pure domain entities + mappers + repository interfaces. Chosen because this
  module has invariants that can be violated (list the main ones).
- **thin** — Eloquent + Action classes. Chosen because this module is CRUD around a
  framework feature and an abstraction layer would add cost without protecting anything.

## Aggregates

| Aggregate | Invariants it protects |
|---|---|
| `Example` | e.g. no duplicate items; only the owner may edit |

## Public surface (what other modules may call)

Other modules must use only these. Everything else is internal.

- Commands: `DoSomething`
- Queries: `GetSomething` → `SomethingView`
- Events published: `SomethingHappened`

## Depends on

| Module | How | Why |
|---|---|---|
| `Vocabulary` | Query `FindTermsByIds` | needs term text for session payloads |

## Ports (outbound interfaces)

| Port | Implementations |
|---|---|
| `SomeProviderPort` | `HttpSomeProvider`, `FakeSomeProvider` (tests) |

## Notes / open questions

Anything a future reader would otherwise have to reverse-engineer: known shortcuts,
planned changes, decisions that looked wrong but were deliberate.
