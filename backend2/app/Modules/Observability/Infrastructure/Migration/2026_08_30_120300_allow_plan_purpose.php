<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `plan` joins the spend whitelist, in the SAME change as the door that emits it — the rule
     * this file's five predecessors each state and keep.
     *
     * Two calls carry it and both run on the CORE model (gpt-5.4): the plan's skeleton (P1, once per
     * plan, ≈$0.021) and each day of it (P2, ≈$0.035–0.050). A three-day plan is about $0.09 before
     * enrichment, which is comparable to a generated collection — and unlike the станок it is fired
     * by a person pressing a button, so its volume is the learner's rather than a run's.
     *
     * ONE value for both, not `plan_outline` and `plan_day`: the question the log answers is «what
     * did this plan cost», and the day's calls already carry the day's collection id from the frame
     * the generator opens. Two labels would split one answer across two rows for no reader.
     *
     * The lesson this list has already paid for once: the CHECK refused `term_reading`,
     * `LogOutboundHttp` swallowed the refusal (deliberately — observability must never break the
     * call it observes), and a paid strong-model call landed in no ledger at all.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation','term_reading','plan'))"
        );
    }

    public function down(): void
    {
        // Same rule as its predecessors: rows written while the value was legal would violate the
        // narrower constraint, so they are relabelled rather than left to fail.
        DB::table('api_request_logs')->where('purpose', 'plan')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation','term_reading'))"
        );
    }
};
