<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE LEARNER'S RIGHTS TO THE PAID PLAN (наряд ACC-1 §2): where each came from (`admin` | `promo` | `apple` |
     * `google`), what it is (`month` | `year` | `lifetime`), where it stands (`active` | `expired` | `grace`), from when
     * and until when (`expires_at` null — no end). One row per learner and source: `access:grant` rewrites the owner's
     * row, the store sync of PAY-1 will write its own beside it; the learner's access is read over all of them
     * (`Identity\Domain\Service\AccessRule`). No purchase is written here by this наряд.
     *
     * Access paths: every read is «the rights of one learner» — the unique (user, source) index serves it. The user FK
     * cascades: the account's deletion takes the rights with the user row.
     */
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->string('source', 16);
            $table->string('product', 16);
            $table->string('status', 16);
            $table->timestampTz('starts_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'source'], 'entitlements_user_source_uidx');
        });
        DB::statement("ALTER TABLE entitlements ADD CONSTRAINT entitlements_source_check CHECK (source IN ('admin', 'promo', 'apple', 'google'))");
        DB::statement("ALTER TABLE entitlements ADD CONSTRAINT entitlements_product_check CHECK (product IN ('month', 'year', 'lifetime'))");
        DB::statement("ALTER TABLE entitlements ADD CONSTRAINT entitlements_status_check CHECK (status IN ('active', 'expired', 'grace'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
