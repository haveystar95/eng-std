<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_collections', function (Blueprint $table): void {
            // Soft-unsubscribe: a null means an active subscription; a timestamp is a per-user
            // tombstone the delta sync ships so the client reconcile drops the collection locally.
            // (Hard delete left no trace for an incremental sync to report.)
            $table->timestampTz('unsubscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_collections', function (Blueprint $table): void {
            $table->dropColumn('unsubscribed_at');
        });
    }
};
