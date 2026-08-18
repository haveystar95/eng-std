<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\ModeRule;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Reads the trainer policy from `learning_mode_settings`: the global rows (`user_id IS NULL`)
 * overridden PER MODE by the user's own rows when there are any.
 *
 * Two ports, one class, because they are one table and one read. Splitting them would mean two
 * queries per request returning halves of the same rows, and — worse — two chances for a session
 * to be dealt under one snapshot of the policy and graded under another.
 *
 * The override is per mode, not per scope: a user row replaces that mode's whole rule (enabled,
 * position and thresholds), and modes they have no row for still inherit. «Inherits» as the panel
 * means it is still «has no rows at all», so the Inherit/Custom switch keeps its meaning.
 *
 * Memoised per instance, which in practice means per request: a study session asks once per card
 * and the answer cannot change mid-request. The container holds this as a singleton, so a queued
 * job that runs for a long time would keep a stale policy — acceptable, since nothing in a job's
 * lifetime depends on a toggle flipped seconds ago.
 */
final class EloquentEnabledModesReader implements EnabledModesReader, ModeAdmissionReader
{
    private const TABLE = 'learning_mode_settings';

    /** @var array<string, list<stdClass>>|null rows by scope key ('' = global) */
    private ?array $rows = null;

    /**
     * Drop the memo. Called by the writer: within one request the admin panel writes a toggle and
     * immediately reads it back to render the result, and a memo taken before the write would show
     * the admin the value they just replaced.
     */
    public function forget(): void
    {
        $this->rows = null;
    }

    // ── EnabledModesReader ───────────────────────────────────────────────────

    public function forUser(UserId $userId): EnabledModes
    {
        return $this->toModes($this->effectiveRows($userId->value));
    }

    public function globalDefault(): EnabledModes
    {
        return $this->toModes($this->scope(''));
    }

    public function overrideFor(UserId $userId): ?EnabledModes
    {
        $own = $this->scope($userId->value);

        return $own === [] ? null : $this->toModes($this->effectiveRows($userId->value));
    }

    // ── ModeAdmissionReader ──────────────────────────────────────────────────

    public function matrixFor(UserId $userId): ModeAdmission
    {
        return $this->toMatrix($this->effectiveRows($userId->value));
    }

    public function globalMatrix(): ModeAdmission
    {
        return $this->toMatrix($this->scope(''));
    }

    // ── reading ──────────────────────────────────────────────────────────────

    /**
     * The global rows with the user's own rows laid over them, mode by mode.
     *
     * @return list<stdClass>
     */
    private function effectiveRows(string $userId): array
    {
        $merged = [];
        foreach ($this->scope('') as $row) {
            $merged[(string) $row->mode] = $row;
        }
        foreach ($this->scope($userId) as $row) {
            $merged[(string) $row->mode] = $row;
        }

        return array_values($merged);
    }

    /** @return list<stdClass> */
    private function scope(string $key): array
    {
        if ($this->rows === null) {
            $this->rows = $this->load();
        }

        return $this->rows[$key] ?? [];
    }

    /** @return array<string, list<stdClass>> */
    private function load(): array
    {
        $byScope = [];
        $rows = DB::table(self::TABLE)->orderBy('position')->orderBy('mode')->get();
        foreach ($rows as $row) {
            $byScope[(string) ($row->user_id ?? '')][] = $row;
        }

        return $byScope;
    }

    /**
     * The enabled set, in rotation order. The order is part of the contract — free practice
     * round-robins by index into it — so it comes from `position`, not from the enum.
     *
     * @param  list<stdClass>  $rows
     */
    private function toModes(array $rows): EnabledModes
    {
        $enabled = array_values(array_filter($rows, static fn (stdClass $r): bool => (bool) $r->enabled));
        usort($enabled, static fn (stdClass $a, stdClass $b): int => [(int) $a->position, (string) $a->mode] <=> [(int) $b->position, (string) $b->mode]);

        $modes = [];
        foreach ($enabled as $row) {
            $mode = ExerciseMode::tryFrom((string) $row->mode);
            // A stored mode this build does not know about (a rollback, or a row written by a newer
            // deploy) is skipped, not fatal: the user trains with the modes that do exist.
            if ($mode === null) {
                Log::warning('learning_mode_settings holds an unknown exercise mode; skipping it', ['mode' => $row->mode]);

                continue;
            }
            $modes[] = $mode;
        }

        if ($modes === []) {
            // The rows are seeded by migration, so an empty set means someone emptied them. Falling
            // back keeps trainers working; saying so loudly is what gets the rows restored.
            Log::error('learning_mode_settings has no enabled modes — falling back to config/learning.php');
            /** @var list<string> $configured */
            $configured = config('learning.enabled_modes', []);
            foreach ($configured as $value) {
                $mode = ExerciseMode::tryFrom($value);
                if ($mode !== null) {
                    $modes[] = $mode;
                }
            }
        }

        return new EnabledModes($modes !== [] ? $modes : [ExerciseMode::MultipleChoice]);
    }

    /**
     * The admission matrix. Note it is built from ALL rows, enabled or not: whether a trainer is
     * switched on and where it sits on the ladder are two independent questions, and the selector
     * asks them separately.
     *
     * @param  list<stdClass>  $rows
     */
    private function toMatrix(array $rows): ModeAdmission
    {
        $rules = [];
        foreach ($rows as $row) {
            $mode = ExerciseMode::tryFrom((string) $row->mode);
            $acquisition = Acquisition::tryFrom((string) $row->min_acquisition);
            if ($mode === null || $acquisition === null) {
                continue;
            }
            $rules[$mode->value] = new ModeRule(
                minAcquisition: $acquisition,
                minLearningStep: $row->min_learning_step !== null ? (int) $row->min_learning_step : null,
                minSuccessfulReviews: $row->min_reps !== null ? (int) $row->min_reps : null,
                optionsPolicy: OptionsPolicy::tryFrom((string) $row->options_policy) ?? OptionsPolicy::Standard,
            );
        }

        if ($rules === []) {
            Log::error('learning_mode_settings holds no admission rules — falling back to the shipped matrix');

            return ModeAdmission::shipped();
        }

        return new ModeAdmission($rules);
    }
}
