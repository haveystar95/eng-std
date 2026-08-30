<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

/**
 * THE GATE: no live vendor adapter is built under `APP_ENV=testing` unless a test asks for one out
 * loud.
 *
 * ## Why this exists, in one paragraph
 *
 * Every port in this module already has a `driver === 'fake'` branch, and `tests/Pest.php` binds
 * fakes over the ports a stray door could reach. Both of those are OPT-OUT protections: they work
 * until something quietly steps around them. In PLAN-1b, something did — two test files bound their
 * fake under `Generation\Application\Port\PlanOutlinePort`, a class name that does not exist (the
 * port lives in `Learning`). The container accepts any string as a key, so the binding registered
 * fine, resolved nothing, and the real adapter stayed in place. Roughly 39 `gpt-5.4` calls went to
 * OpenAI from the test suite, the ledger rows were written into the test database and rolled back
 * with it, and the only symptom was that a test took seven seconds
 * ({@see docs/session-handoff.md}).
 *
 * So this is the OPT-IN half: the last thing between a test run and a vendor's invoice, placed
 * where the adapter is actually CONSTRUCTED rather than where somebody remembered to check a flag.
 *
 * ## The opt-in is deliberate, not a loophole
 *
 * Three test files genuinely want the real adapter — with `Http::fake()` underneath, so nothing
 * reaches the wire — because the adapter itself is the subject: what it puts in the request body,
 * which model name it sends, how it reads the response. Those call `allowLiveAdapters()`, which is
 * greppable, is one line, and reads as a decision. A gate with no way through would have been
 * removed the first time it got in the way, which is how gates die.
 */
final class LiveModelGuard
{
    /** Set by `allowLiveAdapters()` in tests that are ABOUT a real adapter. */
    public const OPT_IN = 'services.generation.allow_live_in_tests';

    /**
     * Refuse to build a live adapter under test, unless this test opted in.
     *
     * @param  string  $what  what was about to be built, in the message a person will read
     *
     * @throws LiveModelRefusedInTests
     */
    public static function refuse(string $what): void
    {
        if (! app()->environment('testing')) {
            return;
        }
        if ((bool) config(self::OPT_IN, false)) {
            return;
        }

        throw LiveModelRefusedInTests::for($what);
    }
}
