<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            // Server-side "has finished first-run onboarding" marker (device-batch F1). Replaces the
            // client keychain flag, which either leaked across accounts (original F1) or re-triggered
            // onboarding on every relogin (the day-1 fix's regression). Null = never onboarded.
            $table->timestamp('onboarded_at')->nullable();
        });

        // Backfill: a profile that was ever edited (updated_at <> created_at) belongs to a user who
        // already went through onboarding — mark it onboarded at that edit time. A pristine profile
        // (never touched since creation) stays null and will see onboarding. Covers the batch account.
        DB::statement('UPDATE profiles SET onboarded_at = updated_at WHERE updated_at <> created_at');
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('onboarded_at');
        });
    }
};
