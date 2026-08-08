<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            // The user's IANA timezone (e.g. "Europe/Kyiv"), sent by the client at login and on
            // profile edits. The scheduler rounds a review's next due_at down to the start of the
            // user's calendar day in this zone (device-batch F19) — an evening review comes back the
            // next morning, not the next evening. Null/absent = UTC (a streak or due computed in UTC
            // breaks for everyone not on UTC). Nullable so existing rows and pristine profiles hold
            // the UTC fallback until the client sends a real zone.
            $table->string('timezone', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
