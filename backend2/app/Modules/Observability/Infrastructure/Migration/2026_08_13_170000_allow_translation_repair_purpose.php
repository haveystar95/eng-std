<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `translation_repair` was missing from the purpose whitelist, and the adapter that emits it
     * (OpenAiTranslationRepairer) predates the constraint. Every repair call the language barrier
     * ever made therefore failed this CHECK on insert, was swallowed by the log writer's catch, and
     * vanished — 256 of them between 2026-08-12 18:47 and 2026-08-13 11:48, including a whole
     * `repair:content-language` sweep whose spend is simply not in the log.
     *
     * The list is a whitelist rather than a free string on purpose (a typo'd purpose is a spend
     * category nobody ever queries), so the fix is to admit the missing member, not to drop the
     * constraint. Adding one is a rewrite of the whole CHECK because Postgres has no "add value" for
     * one.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair'))"
        );
    }

    public function down(): void
    {
        // Reversible only as far as the data allows: rows written while the value was legal would
        // violate the older constraint, so they are relabelled to the nearest truthful category
        // (a repair IS part of a generation) rather than deleted.
        DB::table('api_request_logs')->where('purpose', 'translation_repair')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen'))"
        );
    }
};
