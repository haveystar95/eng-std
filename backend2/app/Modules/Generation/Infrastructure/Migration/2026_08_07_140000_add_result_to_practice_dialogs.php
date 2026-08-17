<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dialog's result, surfaced by GET /practice/collections/{id}/last-dialog: when it ended
 * (`finished_at`, set on finish or expiry — an early exit is still a result) and the native-language
 * recap (`summary`, written on finish; null for an expired-only dialog). Word counts are derived
 * from the stored transcript, so they need no column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_dialogs', function (Blueprint $table): void {
            $table->timestampTz('finished_at')->nullable()->after('expires_at');
            $table->text('summary')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('practice_dialogs', function (Blueprint $table): void {
            $table->dropColumn(['finished_at', 'summary']);
        });
    }
};
