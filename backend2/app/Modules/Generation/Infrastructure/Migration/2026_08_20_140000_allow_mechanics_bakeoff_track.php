<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A fourth sandbox track: `m` — machinery generated over a core that already exists.
 *
 * The CHECK constraint is widened rather than dropped. It is what keeps the report's grouping
 * honest — a track column that accepted free text would let one run write `m` and the next `mech`,
 * and the comparison would quietly split in two.
 */
return new class extends Migration
{
    private const TRACKS = ['a', 'b', 'c', 'm'];

    private const TABLES = ['bakeoff_calls', 'bakeoff_candidates'];

    public function up(): void
    {
        $this->apply(self::TRACKS);
    }

    public function down(): void
    {
        // Rows written on the new track would violate the narrower constraint, so they go first.
        // They are sandbox candidates — reproducible by re-running, and never content.
        DB::table('bakeoff_candidates')->where('track', 'm')->delete();
        DB::table('bakeoff_calls')->where('track', 'm')->delete();

        $this->apply(['a', 'b', 'c']);
    }

    /** @param list<string> $tracks */
    private function apply(array $tracks): void
    {
        $list = "'" . implode("','", $tracks) . "'";
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_track_check");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_track_check CHECK (track IN ({$list}))");
        }
    }
};
