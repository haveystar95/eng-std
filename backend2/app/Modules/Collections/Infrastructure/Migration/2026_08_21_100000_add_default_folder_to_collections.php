<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ONE of a user's own folders may be the DEFAULT one — «Сохранённые», where a word saved from
     * search lands when the learner picked no folder at all.
     *
     * A flag on the collection rather than a column on the user, because the folder is an ordinary
     * custom collection in every other respect: it is owned, it is renameable, free practice runs
     * over it, and its words sit in the pool on the same terms as any other. The only two things
     * the flag buys are «this is where an unaddressed save goes» and «this one cannot be deleted»
     * ({@see \App\Modules\Collections\Domain\Entity\Collection::assertDeletableBy()}).
     *
     * It is created LAZILY — a user who never saves a word from search never gets an empty folder
     * on their shelf — which is why nothing is backfilled here.
     *
     * The partial unique index is the whole guarantee: two default folders would make «where did my
     * word go» a question with two answers. Scoped to live rows (`deleted_at IS NULL`) so a folder
     * that is somehow soft-deleted does not block a replacement, and to owned rows (`owner_id IS
     * NOT NULL`) so the ownerless store catalogue is not dragged into the constraint at all.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_premium');
        });

        DB::statement(
            'CREATE UNIQUE INDEX collections_default_owner_uidx ON collections (owner_id) '
            . 'WHERE is_default AND owner_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS collections_default_owner_uidx');

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn('is_default');
        });
    }
};
