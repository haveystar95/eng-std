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
            $table->string('tier', 16)->default('free');   // free | premium (StoreKit-validated later)
        });

        DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_tier_check CHECK (tier IN ('free','premium'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE profiles DROP CONSTRAINT IF EXISTS profiles_tier_check');

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('tier');
        });
    }
};
