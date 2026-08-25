<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `term_reading` joins the spend whitelist, in the SAME change as the door that emits it — the
     * rule this file's four predecessors each state and keep.
     *
     * It is the most expensive call on the list per token: the reading hint runs on the CORE model
     * (gpt-5.4), because judging how a word sounds is what the A/B bought the strong model for. And
     * unlike the collection станок, it is fired by a TAP — every «Собрать карточку» and every word
     * typed into a folder — so its volume is the learner's, not a run's.
     *
     * Written after the live check found the rows arriving and the calls not: the CHECK refused
     * `term_reading`, `LogOutboundHttp` swallowed the refusal (deliberately — observability must
     * never break the call it observes), and the result was a paid strong-model call in no ledger at
     * all. That is exactly the state the whitelist exists to prevent, one value short of covering.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation','term_reading'))"
        );
    }

    public function down(): void
    {
        // Same rule as its predecessors: rows written while the value was legal would violate the
        // narrower constraint, so they are relabelled rather than left to fail. A reading is not a
        // generation, but `generation` is the older constraint's only bucket for «a call we made to
        // a vendor», and losing the row would be worse than filing it under a neighbour.
        DB::table('api_request_logs')->where('purpose', 'term_reading')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation'))"
        );
    }
};
