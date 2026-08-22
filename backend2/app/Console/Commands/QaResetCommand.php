<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesQaUser;
use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Put a QA account back to «has never studied anything».
 *
 * The counterpart of `qa:time-travel`: that one makes history, this one throws it away. Between
 * them the QA account never has to be believed — a run that ends in a state nobody can explain is
 * reset and re-walked, rather than reasoned about.
 *
 * ── What it clears, and what it deliberately does not ───────────────────────────────────────────
 *
 * Cleared: everything the Learning module holds — progress (and with it the pool, the ladder rung
 * and the schedule), the review log, the triage log, exposures, sessions, derived daily stats.
 * That is exactly {@see LearningAccountEraser}, the same port account deletion uses, so this
 * command cannot drift away from what «all of a user's learning» means. Also cleared: the
 * account's per-user exercise-mode overrides, so a run starts on the shipped admission matrix.
 *
 * NOT cleared: the account itself, its profile (timezone, native language, daily goal, tier), its
 * collections and its terms. A reset that also deleted the account would mean logging in again and
 * re-adding words before every scenario, and the words are not what is being reset.
 *
 * Terms and collections survive on purpose too: they are content, shared and deduplicated across
 * the whole database, and «reset my learning» must never be a path that touches content.
 */
final class QaResetCommand extends Command
{
    use ResolvesQaUser;

    protected $signature = 'qa:reset
        {user : the QA account — email or ULID}
        {--force : skip the confirmation prompt (non-interactive runs)}';

    protected $description = 'QA(non-prod): wipe one QA account\'s learning state (progress, reviews, triages, sessions, stats)';

    public function __construct(private readonly LearningAccountEraser $eraser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $user = $this->resolveQaUser((string) $this->argument('user'));
        if ($user === null) {
            return self::FAILURE;
        }

        $userId = (string) $user->id;
        $before = $this->counts($userId);

        $this->warn("About to WIPE the learning state of {$user->email}.");
        foreach ($before as $table => $n) {
            $this->line(sprintf('  %-24s %5d rows → 0', $table, $n));
        }
        $this->line('  Kept: the account, its profile, its collections and every term.');

        if (! $this->confirmedByForceOrPrompt('Proceed? This deletes rows and cannot be undone.')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($userId): void {
            $this->eraser->eraseFor(UserId::fromString($userId));
            // Per-user mode overrides are Learning's too, but they are settings rather than
            // learning state, so the account eraser leaves them; a QA reset wants the shipped
            // matrix back, or the next run silently drills a different set of trainers.
            DB::table('learning_mode_settings')->where('user_id', $userId)->delete();
        });

        $this->info("Reset {$user->email}. The device must sign out and back in, or full-resync, to drop its mirror.");

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function counts(string $userId): array
    {
        $tables = [
            'user_term_progress',
            'reviews',
            'term_triages',
            'term_exposures',
            'study_sessions',
            'daily_user_stats',
            'learning_mode_settings',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->where('user_id', $userId)->count();
        }

        return $counts;
    }
}
