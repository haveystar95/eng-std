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
        // One row per HTTP call, both directions:
        //  - inbound: a client request to our API (written from a terminable middleware);
        //  - outbound: our call to an external service, e.g. OpenAI (written from an Http
        //    client event listener).
        // Bodies/headers are stored already redacted — secrets never reach this table.
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();                    // server ULID
            $table->string('direction', 8);                       // inbound | outbound
            $table->string('method', 10);
            $table->string('host')->nullable();                   // outbound target host
            $table->text('path');
            $table->string('service', 40)->nullable();            // friendly tag, e.g. 'openai'
            $table->integer('status')->nullable();                // null when the call never completed
            $table->integer('duration_ms')->nullable();
            $table->char('user_id', 26)->nullable();              // inbound authenticated user
            $table->integer('request_bytes')->nullable();
            $table->integer('response_bytes')->nullable();
            $table->jsonb('request_headers')->nullable();         // redacted
            $table->jsonb('request_body')->nullable();            // redacted
            $table->jsonb('response_body')->nullable();           // redacted
            $table->text('error')->nullable();                    // transport error / exception message
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->nullable();        // server write time
        });

        DB::statement("ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_direction_check CHECK (direction IN ('inbound','outbound'))");
        DB::statement('CREATE INDEX api_request_logs_occurred_idx ON api_request_logs (occurred_at DESC)');
        DB::statement('CREATE INDEX api_request_logs_direction_idx ON api_request_logs (direction, occurred_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
