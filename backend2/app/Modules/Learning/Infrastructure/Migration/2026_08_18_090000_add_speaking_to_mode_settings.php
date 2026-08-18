<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The SPEAKING RECALL trainer gets its row in the trainer registry — one per scope, exactly
     * like the nine that came before it.
     *
     * Switched OFF, deliberately and in every scope, including the owner's. A new trainer ships
     * dark and is turned on себе → бете → всем from the admin panel (the release rule in
     * CLAUDE.md): code in main is not a mode on anybody's phone. The row still has to exist while
     * switched off, because {@see ModeAdmission::allows()} is fail-closed — a trainer with no row
     * is undealable rather than universally available, and the panel can only offer a toggle for
     * something the table knows about.
     *
     * The threshold is the shipped one: `graduated`, no learning step, no reps — the assembly rung,
     * beside word_bank and cloze. Speaking's LATE form (read the pinned example aloud instead of
     * saying the word) needs no row of its own and gets none: it is the same trainer asking its
     * question differently further up the ladder, decided by
     * {@see ExerciseMode::gradesAgainstExample()}, the same way the recognition card's two
     * directions are one mode. A second row would put one trainer in the registry twice and hand
     * the owner two switches for one thing.
     *
     * `position` is set per scope to «after everything else currently there», so switching speaking
     * on later appends it to the rotation rather than renumbering the modes a word is already
     * partway through.
     *
     * WHY THIS IS AN UPSERT and not a plain insert. The migration that exploded this table into one
     * row per (scope, mode) walks `ExerciseMode::cases()` — the LIVE enum — so on a database built
     * from scratch today it already writes a speaking row itself, at whatever position the enum
     * order happens to give it. On the deployed database it ran long ago and knows nothing about
     * this trainer. Both histories have to end at the same place, so this migration states the end
     * state rather than assuming which of the two it is walking into: the row exists, it is off,
     * and it is last.
     */
    public function up(): void
    {
        $rule = ModeAdmission::shipped()->ruleFor(ExerciseMode::Speaking);
        $speaking = ExerciseMode::Speaking->value;

        foreach ($this->scopes() as $userId => $position) {
            $scope = DB::table('learning_mode_settings')->where('mode', $speaking);
            $userId !== '' ? $scope->where('user_id', $userId) : $scope->whereNull('user_id');

            $existing = (clone $scope)->first();
            if ($existing !== null) {
                // Written by the explode migration on a fresh database. Only the ordering is this
                // migration's to state — the thresholds it wrote came from the same shipped matrix.
                $scope->update(['position' => $position, 'updated_at' => now()]);

                continue;
            }

            DB::table('learning_mode_settings')->insert([
                'id' => (string) Ulid::generate(),
                'user_id' => $userId !== '' ? $userId : null,
                'mode' => $speaking,
                'enabled' => false,
                'position' => $position,
                'min_acquisition' => $rule?->minAcquisition->value ?? Acquisition::Graduated->value,
                'min_learning_step' => $rule?->minLearningStep,
                'min_reps' => $rule?->minSuccessfulReviews,
                'options_policy' => $rule?->optionsPolicy->value ?? OptionsPolicy::Standard->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Every scope that has rows today, mapped to the position speaking should take in it — one past
     * the last row that is NOT speaking, so running this twice does not walk the row up the list.
     *
     * Derived rather than assumed: the global scope always exists, but a user with per-mode
     * overrides has their own set of rows, and giving them a row too is what keeps «inherits» =
     * «has no rows at all» true for them. A scope with no rows is not invented here — that user
     * inherits, and inheriting a switched-off trainer is switched off.
     *
     * @return array<string, int>  scope key ('' = global) => the position for speaking
     */
    private function scopes(): array
    {
        $next = [];
        $rows = DB::table('learning_mode_settings')
            ->where('mode', '!=', ExerciseMode::Speaking->value)
            ->get(['user_id', 'position']);
        foreach ($rows as $row) {
            $key = (string) ($row->user_id ?? '');
            $next[$key] = max($next[$key] ?? 0, ((int) $row->position) + 1);
        }

        // A table that is somehow empty still needs the product default to exist, or every user
        // falls back to config and the panel has nothing to list.
        return $next === [] ? ['' => 0] : $next;
    }

    /**
     * Reversible: the trainer leaves the registry entirely, in every scope.
     *
     * The inverse of «the speaking trainer joins the registry», which is what this migration is —
     * and the right one to pair with the check-constraint migration beside it, which drops speaking
     * answers on the way down. Rolling this back rolls back the code that deals the card, so a row
     * for a trainer that no longer exists would be the surprising outcome, not the tidy one.
     */
    public function down(): void
    {
        DB::table('learning_mode_settings')->where('mode', ExerciseMode::Speaking->value)->delete();
    }
};
