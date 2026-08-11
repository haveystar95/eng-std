<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reads the trainer toggles from `learning_mode_settings`: the global row (`user_id IS NULL`)
 * overridden by the user's own row when there is one.
 *
 * Memoised per instance, which in practice means per request: a study session asks once per card
 * assembly and the answer cannot change mid-request. The service container holds this as a
 * singleton, so a queued job that runs for a long time would keep a stale set — acceptable, since
 * nothing in a job's lifetime depends on a toggle flipped seconds ago.
 */
final class EloquentEnabledModesReader implements EnabledModesReader
{
    private ?EnabledModes $global = null;

    /** @var array<string, EnabledModes|null> */
    private array $overrides = [];

    /**
     * Drop the memo. Called by the writer: within one request the admin panel writes a toggle and
     * immediately reads it back to render the result, and a memo taken before the write would show
     * the admin the value they just replaced.
     */
    public function forget(): void
    {
        $this->global = null;
        $this->overrides = [];
    }

    public function forUser(UserId $userId): EnabledModes
    {
        return $this->overrideFor($userId) ?? $this->globalDefault();
    }

    public function globalDefault(): EnabledModes
    {
        if ($this->global !== null) {
            return $this->global;
        }

        $modes = $this->read(null);
        if ($modes === null) {
            // The seed row is created by migration, so this means someone deleted it. Falling back
            // to config keeps trainers working; saying so loudly is what gets the row restored.
            Log::error('learning_mode_settings has no global row — falling back to config/learning.php');
            /** @var list<string> $configured */
            $configured = config('learning.enabled_modes', []);
            $modes = $this->toModes($configured);
        }

        return $this->global = $modes;
    }

    public function overrideFor(UserId $userId): ?EnabledModes
    {
        if (array_key_exists($userId->value, $this->overrides)) {
            return $this->overrides[$userId->value];
        }

        return $this->overrides[$userId->value] = $this->read($userId->value);
    }

    private function read(?string $userId): ?EnabledModes
    {
        $row = DB::table('learning_mode_settings')
            ->when($userId === null,
                static fn ($q) => $q->whereNull('user_id'),
                static fn ($q) => $q->where('user_id', $userId),
            )
            ->value('modes');

        if (! is_string($row)) {
            return null;
        }

        $decoded = json_decode($row, true);
        if (! is_array($decoded) || $decoded === []) {
            return null; // a corrupt or empty row inherits rather than breaking every card
        }

        /** @var list<string> $values */
        $values = array_values(array_filter($decoded, 'is_string'));

        return $values === [] ? null : $this->toModes($values);
    }

    /** @param list<string> $values */
    private function toModes(array $values): EnabledModes
    {
        $modes = [];
        foreach ($values as $value) {
            $mode = ExerciseMode::tryFrom($value);
            // A stored mode this build does not know about (a rollback, or a row written by a newer
            // deploy) is skipped, not fatal: the user trains with the modes that do exist.
            if ($mode === null) {
                Log::warning('learning_mode_settings holds an unknown exercise mode; skipping it', ['mode' => $value]);

                continue;
            }
            $modes[] = $mode;
        }

        return new EnabledModes($modes !== [] ? $modes : [ExerciseMode::MultipleChoice]);
    }
}
