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
        // Append-only transcript of a practice dialog. (dialog_id, role, ts) is unique so a
        // re-uploaded batch inserts only new lines — the transcript POST is safely retryable.
        Schema::create('practice_dialog_messages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('dialog_id', 26);
            $table->string('role', 16);                   // user | assistant
            $table->text('text');
            $table->bigInteger('ts');                     // client line timestamp (ms)
            $table->timestampTz('created_at');

            $table->unique(['dialog_id', 'role', 'ts'], 'practice_dialog_messages_idem');
        });

        DB::statement("ALTER TABLE practice_dialog_messages ADD CONSTRAINT practice_dialog_messages_role_check CHECK (role IN ('user','assistant'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_dialog_messages');
    }
};
