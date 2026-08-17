<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only trail of every admin mutation (who did what to whom, when). v1 has one
        // mutation — flipping a user's tier — but the table is the funnel every future admin write
        // goes through: an admin action that changes app state must leave a row here.
        Schema::create('admin_audit_log', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('admin_id', 26);                  // the acting admin
            $table->string('action', 48);                  // e.g. user.tier.change
            $table->char('target_user_id', 26)->nullable(); // the affected app user, when any
            $table->jsonb('context')->nullable();          // before/after, request detail
            $table->timestampTz('created_at');

            $table->index(['admin_id', 'created_at'], 'admin_audit_log_admin_idx');
            $table->index(['target_user_id', 'created_at'], 'admin_audit_log_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log');
    }
};
