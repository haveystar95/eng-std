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
        // One realtime conversation-practice session over a collection. `lesson_json` is the brief
        // the model was started with (topic, level, langs, target words, rules). `cost_usd` is an
        // ESTIMATE written when the dialog leaves `active` (we never see the realtime usage event).
        Schema::create('practice_dialogs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('collection_id', 26);            // cross-module ref, intentionally no FK
            $table->string('status', 16);                 // active | finished | expired
            $table->jsonb('lesson_json');
            $table->timestampTz('expires_at');
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestampTz('created_at');

            // The quota's access path: a user's dialogs started within a day.
            $table->index(['user_id', 'created_at'], 'practice_dialogs_user_idx');
            // The expiry sweep: still-active dialogs past their TTL.
            $table->index(['status', 'expires_at'], 'practice_dialogs_expiry_idx');
        });

        DB::statement("ALTER TABLE practice_dialogs ADD CONSTRAINT practice_dialogs_status_check CHECK (status IN ('active','finished','expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_dialogs');
    }
};
