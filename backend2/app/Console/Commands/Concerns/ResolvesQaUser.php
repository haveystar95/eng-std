<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The one place the QA tooling decides «may I touch this account».
 *
 * Two questions, and both have to answer yes:
 *
 *   1. is this a non-production environment? (a wipe command with no environment check is a
 *      production incident waiting for a mistyped host)
 *   2. is the account marked `is_qa`? — the column added by the 2026-08-22 migration, defaulted to
 *      false, so no account that exists today can be reached by any of this.
 *
 * A trait and not a copy per command, because the second command is where a re-typed guard drifts.
 * Every `qa:*` command that WRITES calls {@see resolveQaUser} and nothing else.
 */
trait ResolvesQaUser
{
    /**
     * The `users` row for a QA account named by email or by ULID, or null after printing why not.
     *
     * Null is the refusal — the caller returns FAILURE. The reason is printed here so all four
     * refusals (wrong environment, no such account, not a QA account, ambiguous) read the same
     * whichever command hit them.
     */
    private function resolveQaUser(string $needle): ?stdClass
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production. The QA tooling rewrites and deletes learning data.');

            return null;
        }

        $needle = trim($needle);
        if ($needle === '') {
            $this->error('Name the user: an email or a ULID.');

            return null;
        }

        /** @var stdClass|null $user */
        $user = DB::table('users')
            ->where('email', $needle)
            ->orWhere('id', $needle)
            ->first();

        if ($user === null) {
            $this->error("No user matches «{$needle}» (looked by email and by id).");

            return null;
        }

        if (! (bool) $user->is_qa) {
            $this->error("«{$needle}» is not a QA account (users.is_qa = false). Refusing.");
            $this->line('  QA accounts are created by the dev sign-in (POST /api/v1/auth/dev) and by nothing else.');

            return null;
        }

        return $user;
    }

    /** Ask before rewriting anything, unless `--force` said not to (non-interactive runs). */
    private function confirmedByForceOrPrompt(string $question): bool
    {
        if ((bool) $this->option('force')) {
            return true;
        }

        return (bool) $this->confirm($question);
    }
}
