<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A DEVICE'S PUSH ADDRESS (наряд PLAN-UI-3). One row per (platform, token): a token is the
     * address of one app install, so when another account signs in on the same phone the row
     * MOVES to that account instead of the phone receiving both learners' letters.
     *
     * Access paths: the sender reads every token of one user (`user_id` index); the upsert and
     * the «this token is dead» delete go by (platform, token) — the unique index. The user FK
     * cascades, so account deletion takes the tokens with the user row, like `profiles`.
     */
    public function up(): void
    {
        Schema::create('device_push_tokens', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->string('platform', 8);
            $table->string('token', 255);
            $table->string('locale', 35)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['platform', 'token'], 'device_push_tokens_address_uidx');
            $table->index('user_id', 'device_push_tokens_user_idx');
        });
        DB::statement("ALTER TABLE device_push_tokens ADD CONSTRAINT device_push_tokens_platform_check CHECK (platform IN ('ios'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
