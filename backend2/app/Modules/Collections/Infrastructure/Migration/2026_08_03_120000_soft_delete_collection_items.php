<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Soft-delete collection items, the same mechanism collections already use, so GET /sync can
    // ship removals as tombstones (op:delete) instead of leaving offline clients with ghosts.
    // Editing a word is remove + add, but editing is an occasional manual action, so dead rows
    // accrue slowly — not the "more dead than live" profile that would argue against soft-delete.
    //
    // The unique(collection_id, term_id) becomes PARTIAL (live rows only) so a removed term can be
    // re-added. Reads via the CollectionItemModel relation are filtered by SoftDeletes; the one raw
    // reader (EloquentUserCollectionTermsReader, powering triage/study) filters deleted_at by hand.
    public function up(): void
    {
        Schema::table('collection_items', function (Blueprint $table) {
            $table->softDeletesTz();
            $table->dropUnique('collection_items_uidx');
        });

        DB::statement('CREATE UNIQUE INDEX collection_items_uidx ON collection_items (collection_id, term_id) WHERE deleted_at IS NULL');
        // Sync reads item deletions by collection set and deleted_at watermark.
        DB::statement('CREATE INDEX collection_items_deleted_idx ON collection_items (collection_id, deleted_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS collection_items_deleted_idx');
        DB::statement('DROP INDEX IF EXISTS collection_items_uidx');
        Schema::table('collection_items', function (Blueprint $table) {
            $table->unique(['collection_id', 'term_id'], 'collection_items_uidx');
            $table->dropSoftDeletes();
        });
    }
};
