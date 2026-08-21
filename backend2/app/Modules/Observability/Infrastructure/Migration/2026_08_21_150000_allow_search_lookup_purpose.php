<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `search_lookup` joins the spend whitelist, BEFORE the adapter that emits it ships.
     *
     * The two migrations before this one (`translation_repair`, `playground`) are the same hole
     * found after the fact: an adapter labels its calls with a value the CHECK does not know, the
     * insert fails, the writer swallows it, and the money is spent with no row to show for it. The
     * lesson is that the whitelist has to move in the SAME change as the adapter, so this migration
     * lands beside {@see \App\Modules\Generation\Infrastructure\Adapter\OpenAiWordLookup} rather
     * than after the first month of bills.
     *
     * It matters more here than for the two before it: the search lookup is the one paid call a
     * LEARNER can trigger by typing, dozens of times a day, and «сколько стоит один lookup» is a
     * question answered from this log or not at all.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup'))"
        );
    }

    public function down(): void
    {
        // Same rule as its two predecessors: rows written while the value was legal would violate
        // the narrower constraint, so they are relabelled rather than left to fail. A lookup is a
        // generation call made from the search field — `generation` is the nearest truthful bucket.
        DB::table('api_request_logs')->where('purpose', 'search_lookup')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground'))"
        );
    }
};
