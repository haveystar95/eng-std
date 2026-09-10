<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WHAT THE FIRST LEARNING PLAN WROTE ONTO SHARED VOCABULARY, taken back off.
     *
     * Thirteen columns on `terms` (line/word/chunk kind, the frame with its slot, whose turn a line
     * was, the shelf and the tier, the speaking key and its simpler forms, the topical mark…), the
     * day-scoped example on `term_examples`, and the `term_audios` cache — every one of them was
     * written by the old plan's day generator and read by nothing else. The new plan keeps its
     * material in its own tables (`plan_terms`, `plan_line_audios`) and hands the collection only
     * ordinary terms, so a global term is a global term again.
     *
     * Guarded column by column: on a fresh database (whose plan migrations were deleted) none of
     * this exists and the migration is a no-op. One-way, like the plan's own drop.
     */
    private const TERM_COLUMNS = [
        'is_line', 'difficulty_score', 'kind', 'frame', 'speaker', 'filler', 'shelf', 'tier',
        'skill_ref', 'number_value', 'speaking_key', 'speaking_keys', 'topical',
    ];

    public function up(): void
    {
        foreach (['kind', 'speaker', 'shelf', 'tier'] as $name) {
            DB::statement("ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_{$name}_check");
        }

        $present = array_values(array_filter(
            self::TERM_COLUMNS,
            static fn (string $column): bool => Schema::hasColumn('terms', $column),
        ));
        if ($present !== []) {
            Schema::table('terms', function (Blueprint $table) use ($present): void {
                $table->dropColumn($present);
            });
        }

        if (Schema::hasColumn('term_examples', 'scope_collection_id')) {
            DB::statement('DROP INDEX IF EXISTS term_examples_scope_idx');
            Schema::table('term_examples', function (Blueprint $table): void {
                $table->dropForeign(['scope_collection_id']);
                $table->dropColumn('scope_collection_id');
            });
        }

        Schema::dropIfExists('term_audios');
    }

    public function down(): void
    {
        // One-way by design — see the class docblock.
    }
};
