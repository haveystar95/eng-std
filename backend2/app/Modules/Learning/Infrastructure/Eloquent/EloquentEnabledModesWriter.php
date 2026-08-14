<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\ModeRule;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentEnabledModesWriter implements EnabledModesWriter
{
    private const TABLE = 'learning_mode_settings';

    /**
     * The reader is a per-request memo and a singleton, so it is the same instance the rest of this
     * request will ask. Handing it to the writer is what makes write-then-read inside one request
     * (which is exactly what the admin panel does) return the new value.
     */
    public function __construct(private readonly EloquentEnabledModesReader $reader) {}

    public function setGlobalDefault(EnabledModes $modes): void
    {
        $this->writeToggles(null, $modes);
    }

    public function setOverrideFor(UserId $userId, ?EnabledModes $modes): void
    {
        if ($modes === null) {
            // Inherit = no rows. Storing the global set as a copy would silently pin the user to
            // today's default and quietly exclude them from tomorrow's.
            DB::table(self::TABLE)->where('user_id', $userId->value)->delete();
            $this->reader->forget();

            return;
        }

        $this->writeToggles($userId->value, $modes);
    }

    public function setRule(?UserId $userId, ExerciseMode $mode, ModeRule $rule): void
    {
        $scope = $userId?->value;
        $existing = $this->row($scope, $mode);

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update([
                ...$this->ruleColumns($rule),
                'updated_at' => now(),
            ]);
            $this->reader->forget();

            return;
        }

        // A user row that does not exist yet inherits the global one's ON/OFF state and position:
        // writing a THRESHOLD must not silently switch a trainer on or reshuffle the rotation.
        $inherited = $this->row(null, $mode);
        DB::table(self::TABLE)->insert([
            'id' => (string) Ulid::generate(),
            'user_id' => $scope,
            'mode' => $mode->value,
            'enabled' => (bool) ($inherited->enabled ?? false),
            'position' => (int) ($inherited->position ?? 0),
            ...$this->ruleColumns($rule),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->reader->forget();
    }

    /**
     * Replace a scope's ON/OFF state and rotation order, leaving every threshold alone — the two
     * are independent settings and the panel edits them on separate screens.
     */
    private function writeToggles(?string $userId, EnabledModes $modes): void
    {
        $positions = [];
        foreach ($modes->modes as $position => $mode) {
            $positions[$mode->value] = $position;
        }

        $shipped = ModeAdmission::shipped();
        foreach (ExerciseMode::cases() as $index => $mode) {
            $enabled = array_key_exists($mode->value, $positions);
            $existing = $this->row($userId, $mode);

            if ($existing !== null) {
                DB::table(self::TABLE)->where('id', $existing->id)->update([
                    'enabled' => $enabled,
                    // A mode being switched off keeps its old position rather than being parked at
                    // the end: switching it back on should restore the rotation, not renumber it.
                    'position' => $enabled ? $positions[$mode->value] : (int) $existing->position,
                    'updated_at' => now(),
                ]);

                continue;
            }

            // No row for this scope yet — for a user override that is the normal case, and the
            // threshold it starts from is the one they were already being dealt under.
            $rule = ($userId !== null ? $this->reader->globalMatrix() : $shipped)->ruleFor($mode)
                ?? $shipped->ruleFor($mode);
            DB::table(self::TABLE)->insert([
                'id' => (string) Ulid::generate(),
                'user_id' => $userId,
                'mode' => $mode->value,
                'enabled' => $enabled,
                'position' => $enabled ? $positions[$mode->value] : count($positions) + $index,
                ...$this->ruleColumns($rule ?? new ModeRule(\App\Modules\Learning\Domain\ValueObject\Acquisition::Graduated)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->reader->forget();
    }

    /** @return array<string, mixed> */
    private function ruleColumns(ModeRule $rule): array
    {
        return [
            'min_acquisition' => $rule->minAcquisition->value,
            'min_learning_step' => $rule->minLearningStep,
            'min_reps' => $rule->minReps,
            'options_policy' => $rule->optionsPolicy->value,
        ];
    }

    private function row(?string $userId, ExerciseMode $mode): ?stdClass
    {
        return DB::table(self::TABLE)
            ->when($userId === null,
                static fn ($q) => $q->whereNull('user_id'),
                static fn ($q) => $q->where('user_id', $userId),
            )
            ->where('mode', $mode->value)
            ->first();
    }
}
