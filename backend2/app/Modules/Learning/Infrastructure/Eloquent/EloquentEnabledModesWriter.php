<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentEnabledModesWriter implements EnabledModesWriter
{
    public function setGlobalDefault(EnabledModes $modes): void
    {
        $this->upsert(null, $modes);
    }

    public function setOverrideFor(UserId $userId, ?EnabledModes $modes): void
    {
        if ($modes === null) {
            // Inherit = no row. Storing the global set as a copy would silently pin the user to
            // today's default and quietly exclude them from tomorrow's.
            DB::table('learning_mode_settings')->where('user_id', $userId->value)->delete();

            return;
        }

        $this->upsert($userId->value, $modes);
    }

    private function upsert(?string $userId, EnabledModes $modes): void
    {
        $values = array_map(static fn (ExerciseMode $m): string => $m->value, $modes->modes);
        $row = DB::table('learning_mode_settings')
            ->when($userId === null,
                static fn ($q) => $q->whereNull('user_id'),
                static fn ($q) => $q->where('user_id', $userId),
            );

        if ($row->exists()) {
            $row->update(['modes' => json_encode($values), 'updated_at' => now()]);

            return;
        }

        DB::table('learning_mode_settings')->insert([
            'id' => Ulid::generate(),
            'user_id' => $userId,
            'modes' => json_encode($values),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
