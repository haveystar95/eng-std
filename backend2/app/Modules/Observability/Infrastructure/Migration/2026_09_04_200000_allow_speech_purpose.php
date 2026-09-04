<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `speech` — озвучка реплик (наряд TTS-1), десятая цель вызова.
     *
     * ПОЙМАНО ЖИВЫМ ПРОГОНОМ, и поймано ровно так, как ловятся такие вещи: никак. Список целей —
     * белый, и не попавшая в него цель отбивается CHECK-ом; отбитую вставку глотает
     * `LogOutboundHttp` («observability must never break the call it is observing»). Итог: озвучка
     * покупалась, деньги тратились, и в панели этих вызовов НЕ БЫЛО ВООБЩЕ — ни строки, ни нуля,
     * ни ошибки. Ровно то же уже случалось с `translation_repair` (миграция 2026_08_13_170000) и с
     * `playground` (2026_08_21_140000); это третий раз.
     *
     * Белым список остаётся сознательно: опечатка в цели — это потерянная статья расходов, а не
     * строка с опечаткой. Цена этого решения — вот такая миграция на каждую новую цель, и она
     * дешевле, чем «мы не знаем, за что заплатили».
     */
    private const PURPOSES = "'generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation','term_reading','plan','speech'";

    private const BEFORE = "'generation','images','enrichment','realtime','recap','example_regen','translation_repair','playground','search_lookup','instant_translation','term_reading','plan'";

    public function up(): void
    {
        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . 'CHECK (purpose IS NULL OR purpose IN (' . self::PURPOSES . '))'
        );
    }

    public function down(): void
    {
        // Строки не удаляем: журнал append-only и это ЗАПИСЬ О ТРАТЕ. Цель переписывается на
        // `generation` — та же форма отката, что у двух предыдущих миграций этого списка.
        DB::table('api_request_logs')->where('purpose', 'speech')->update(['purpose' => 'generation']);

        DB::statement('ALTER TABLE api_request_logs DROP CONSTRAINT IF EXISTS api_request_logs_purpose_check');
        DB::statement(
            'ALTER TABLE api_request_logs ADD CONSTRAINT api_request_logs_purpose_check '
            . 'CHECK (purpose IS NULL OR purpose IN (' . self::BEFORE . '))'
        );
    }
};
