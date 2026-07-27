---
name: testing-pest
description: Testing strategy and conventions for this modular Laravel backend — Pest, the test pyramid across Domain/Application/Presentation layers, architecture tests that enforce module boundaries, fakes for the AI provider, and what must be covered before a task is done. Consult this skill whenever writing tests, being asked "is this tested", adding a feature that needs coverage, or setting up CI checks.
---

# Testing

Pest 3 + PHPStan level 8 + Deptrac. `composer check` runs all three; a task isn't done
until it passes.

## The pyramid, mapped onto the layers

| Layer | Test type | DB? | What it proves |
|---|---|---|---|
| `Domain` | unit | no | rules are correct (scheduling, dedup, ownership) |
| `Application` | integration | yes | handler + repository wiring works |
| `Presentation` | feature | yes | contract, auth, validation, status codes |
| repo-wide | architecture | no | boundaries haven't rotted |

Most tests should be domain unit tests. They're fast, they don't need a database, and
they're where actual bugs live. If a module has 40 feature tests and 3 unit tests, the
logic is probably sitting in the wrong layer — fix the design, not the test count.

## Domain unit tests

Pure, no `RefreshDatabase`, no container. Table-driven where the rules are numeric:

```php
dataset('sm2 grades', [
    'good on new term'      => ['new',   Grade::Good,  'learning', 1],
    'good graduates'        => ['learning', Grade::Good, 'review',  4],
    'again lapses'          => ['review', Grade::Again, 'relearning', 0],
    'ease floor respected'  => ['review', Grade::Hard,  'review',  null],
]);

it('schedules correctly', function (string $state, Grade $grade, string $expected, ?int $days) {
    $progress = TermProgress::fromState($state, easeFactor: 1.30, intervalDays: 10);
    $result = (new Sm2Scheduler(fuzz: Fuzz::none()))->schedule($progress, $grade, $this->now);

    expect($result->state()->value)->toBe($expected);
    if ($days !== null) expect($result->intervalDays())->toBe($days);
})->with('sm2 grades');
```

Inject a fixed `Clock` and a disabled fuzz source — a test that fails once a month
because of randomness gets muted, and then it protects nothing.

## Application integration tests

Test the handler with real repositories against the DB, but fake every outbound port:

```php
beforeEach(fn () => app()->bind(CollectionGeneratorPort::class, FakeCollectionGenerator::class));
```

No test ever calls a real LLM. The fake returns a fixed draft, plus modes for
`returns_invalid_json`, `returns_wrong_language`, `times_out` — the failure paths are the
ones worth testing, since the happy path is obvious and the failure path is what users hit.

## Feature tests

One per endpoint, covering: success, 403 (wrong owner / shared collection edit),
422 (validation), and the empty-result case. Plus, for anything the mobile client retries:

- posting the same review batch twice changes nothing the second time
- an out-of-order offline batch produces the same progress as an in-order one
- a client-supplied ULID that already exists returns 200, not 500

## Architecture tests

These are cheap and catch the drift that reviews miss:

```php
arch('domain is framework-free')
    ->expect('App\Modules\*\Domain')
    ->not->toUse(['Illuminate', 'Eloquent', 'Carbon']);

arch('modules do not reach into each other')
    ->expect('App\Modules\Collections')
    ->not->toUse('App\Modules\Learning\Infrastructure');

arch('controllers stay thin')
    ->expect('App\Modules\*\Presentation\Http\Controller')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('everything is strict')->expect('App\Modules')->toUseStrictTypes();
```

## Fixtures and factories

- Factories live in `Infrastructure/Eloquent/Factory` per module, and build **valid**
  aggregates by default.
- Domain object builders (`TermProgressBuilder::new()->withInterval(10)->build()`) for
  unit tests — factories that touch the DB have no business in domain tests.
- Seed data for generation quality checks in `tests/Fixtures/generation-prompts.json`.

## What CI runs

```
composer arch   # deptrac + pest arch tests
composer stan   # phpstan level 8
composer test   # pest, parallel
```

Plus a migration check: `migrate:fresh` then `migrate:rollback` on a clean database, so a
non-reversible migration is caught before production.

## Coverage expectations

Not a percentage target — a rule about *what*:
`Domain/Service` and `Domain/Entity` should be near-fully covered; the scheduler
especially. Controllers need one test each. Mappers and providers need none.
