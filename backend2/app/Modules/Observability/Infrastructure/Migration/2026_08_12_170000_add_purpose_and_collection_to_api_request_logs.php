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
        // WHY a call was made, and WHAT it was made for. Without these two columns an outbound row
        // says "we posted to api.openai.com/v1/chat/completions" and nothing about which product
        // feature (or whose collection) is spending the money — which is the whole point of the
        // log for unit economics. Both are nullable: inbound rows have no purpose, and plenty of
        // outbound calls (a term enrichment run over the whole dictionary) belong to no collection.
        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->string('purpose', 24)->nullable()->after('service');
            $table->char('collection_id', 26)->nullable()->after('purpose');
        });

        DB::statement(
            "ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check "
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen'))"
        );
        DB::statement('CREATE INDEX api_request_logs_purpose_idx ON api_request_logs (purpose, occurred_at DESC)');
        DB::statement('CREATE INDEX api_request_logs_collection_idx ON api_request_logs (collection_id, occurred_at DESC) WHERE collection_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS api_request_logs_collection_idx');
        DB::statement('DROP INDEX IF EXISTS api_request_logs_purpose_idx');
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');

        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'collection_id']);
        });
    }
};
