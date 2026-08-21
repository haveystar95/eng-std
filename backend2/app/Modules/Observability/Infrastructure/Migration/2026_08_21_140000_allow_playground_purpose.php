<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The same defect as `translation_repair` (2026_08_13_170000), one adapter later: the sandbox's
     * two model adapters label their calls `playground`
     * ({@see \App\Modules\Generation\Infrastructure\Adapter\AnthropicPlaygroundModel},
     * {@see \App\Modules\Generation\Infrastructure\Adapter\OpenAiCompatiblePlaygroundModel}), the
     * whitelist never learned the value, and so every sandbox call ever made failed this CHECK on
     * insert and was swallowed by the writer's catch.
     *
     * It is not a cosmetic hole. The screen tells the operator «вызов модели стоит денег и попадает в
     * общий журнал расходов» — a promise the log could not keep — and a model comparison run in the
     * sandbox leaves no trace to go back to: the answers are on screen until the tab is closed, and
     * nowhere afterwards. Both halves of «сколько мы потратили» and «что именно ответила модель»
     * depend on the row this constraint was rejecting.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground'))"
        );
    }

    public function down(): void
    {
        // Same rule as the migration this repeats: rows written while the value was legal would
        // violate the older constraint. A sandbox call is not a spend category of its own once the
        // value is gone, and `generation` is the nearest truthful one — it IS a generation call,
        // made by hand.
        DB::table('api_request_logs')->where('purpose', 'playground')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . "CHECK (purpose IS NULL OR purpose IN ('generation','images','enrichment','realtime','recap','example_regen','translation_repair'))"
        );
    }
};
