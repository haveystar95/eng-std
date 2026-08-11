<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which trainers are switched on, as DATA instead of a deploy.
     *
     * One row per scope: `user_id NULL` is the product default, a row with a user id overrides it
     * for that user. An absent user row means "inherit", which is different from an empty set and
     * is why the override is a missing row rather than a nullable column.
     *
     * `modes` is an ORDERED list. The order is part of the contract — free practice round-robins by
     * index into it, on the server and mirrored on the device — so it is stored as a JSON array,
     * not as a set.
     *
     * This is the thin slice of the deferred learning policy: only the mode set moves into the
     * database, scheduling stays exactly where it is (see docs/specs/trainers-v3.md, «Часть 2»).
     */
    public function up(): void
    {
        Schema::create('learning_mode_settings', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26)->nullable();
            $table->jsonb('modes');
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // One row per scope. Postgres treats every NULL as distinct in a unique index, so the
        // global row would not be protected without folding NULL to a value first.
        DB::statement("CREATE UNIQUE INDEX learning_mode_settings_scope_uidx ON learning_mode_settings (COALESCE(user_id::text, ''))");

        // Seed the global default from the config the app has been running on, so switching the
        // source of truth to the database changes nothing for anyone until a toggle is flipped.
        /** @var list<string> $modes */
        $modes = config('learning.enabled_modes', []);
        DB::table('learning_mode_settings')->insert([
            'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'user_id' => null,
            'modes' => json_encode(array_values($modes)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_mode_settings');
    }
};
