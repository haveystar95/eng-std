<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `instant_translation` joins the spend whitelist, in the SAME change as the adapter that emits
     * it — the rule learned three migrations ago and kept since.
     *
     * This one is worth stating plainly because the call costs NO DOLLARS: DeepL's free plan bills
     * in characters, not money, so a `cost_usd` report will show zeroes here forever. That is
     * exactly why the row has to exist. The thing this feature can run out of is not a budget in
     * dollars but half a million characters a month, and «which calls did we make, how often, and
     * how long did they take» is answerable from this log or from nowhere. Spend that appears in no
     * ledger is spend nobody watches approach its ceiling.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation'))"
        );
    }

    public function down(): void
    {
        // Same rule as its predecessors: rows written while the value was legal would violate the
        // narrower constraint, so they are relabelled rather than left to fail. A machine
        // translation is not a generation, but `generation` is the only bucket the older constraint
        // has for «a call we made to a vendor», and losing the row entirely would be worse.
        DB::table('api_request_logs')->where('purpose', 'instant_translation')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup'))"
        );
    }
};
