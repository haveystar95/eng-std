<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The admin's plan page (наряд ADM-1) reads a plan's client calls by the path they were made to — `api/v1/plans/<id>%`,
 * a day's document exactly — and counts them. Without an index on the path that was a scan of the whole log (≈ 90 ms on
 * 49 000 rows, and the log only grows). `text_pattern_ops` serves the prefix LIKE and the equality; the index holds only
 * inbound rows, the only ones asked about by path. An index only — nothing is written to a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS api_request_logs_inbound_path_idx ON api_request_logs (path text_pattern_ops) WHERE direction = 'inbound'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS api_request_logs_inbound_path_idx');
    }
};
