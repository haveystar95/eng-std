<?php

declare(strict_types=1);

/**
 * SESSION-1a · EXPLAIN FOR THE THREE READ AND WRITE PATHS THE ORDER NAMES — on the e2e database, after the doctor's day
 * is dealt and the judge smoke has run:
 *
 *  1. the day's cards in walking order — `DayCardRepository::forDay` (GET day, open, answer, judge);
 *  2. the slot judge's counter upsert — `CheckCounters::recordCodes('slot_judge.v1', ['judge.unavailable'])`;
 *  3. the voice of the day's scenes — `LineAudioStore::forScenes` (every card and window read).
 *
 * The statements are captured from the repositories themselves (`DB::listen`), then explained with their bindings;
 * the upsert is explained with ANALYZE inside a transaction that is rolled back — nothing is written.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/session-1a/tools/explain.php > docs/research/session-1a/explain.txt
 */

use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) config('database.connections.pgsql.database') === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}

$planId = getenv('SMOKE_PLAN') ?: '01M2H13E1QT6F5D4FKJSEKTAD7';
$day = DB::table('plan_days')->where('plan_id', $planId)->where('number', 1)->first();
$scenes = DB::table('plan_scenes')->where('plan_id', $planId)->pluck('id')->all();
$voices = DB::table('plan_line_audios')->whereIn('scene_id', $scenes)->distinct()->pluck('voice_key')->all();

DB::statement('ANALYZE day_cards');
DB::statement('ANALYZE plan_check_counters');
DB::statement('ANALYZE plan_line_audios');
echo 'rows: day_cards='.DB::table('day_cards')->count().' plan_check_counters='.DB::table('plan_check_counters')->count().' plan_line_audios='.DB::table('plan_line_audios')->count()."\n\n";
foreach (['day_cards', 'plan_check_counters', 'plan_line_audios'] as $table) {
    foreach (DB::select('select indexname, indexdef from pg_indexes where tablename = ? order by indexname', [$table]) as $index) {
        echo "  {$index->indexname}: {$index->indexdef}\n";
    }
}
echo "\n";

/** @return list<array{sql: string, bindings: array<int, mixed>}> */
function captured(callable $run): array
{
    $queries = [];
    DB::listen(static function (QueryExecuted $q) use (&$queries): void {
        $queries[] = ['sql' => $q->sql, 'bindings' => $q->bindings];
    });
    $run();
    DB::getEventDispatcher()?->forget(QueryExecuted::class);

    return $queries;
}

function explain(string $title, array $query, bool $analyze = true): void
{
    echo "=== {$title}\n    {$query['sql']}\n";
    $prefix = $analyze ? 'EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) ' : 'EXPLAIN (FORMAT TEXT) ';
    foreach (DB::select($prefix.$query['sql'], $query['bindings']) as $row) {
        echo '    '.trim((string) $row->{'QUERY PLAN'})."\n";
    }
    echo "\n";
}

foreach (captured(static fn () => app(DayCardRepository::class)->forDay(PlanDayId::fromString((string) $day->id))) as $q) {
    explain('1. day cards by day, in stage order and position (forDay)', $q);
}

DB::beginTransaction();
try {
    foreach (captured(static fn () => app(CheckCounters::class)->recordCodes('slot_judge.v1', ['judge.unavailable'])) as $q) {
        explain('2. slot judge counter upsert (recordCodes, rolled back)', $q);
    }
} finally {
    DB::rollBack();
}

foreach (captured(static fn () => app(LineAudioStore::class)->forScenes($scenes, $voices)) as $q) {
    explain('3. the voice of the scenes (forScenes)', $q);
}

// On a table this small the planner may prefer a sequential scan whatever the index; the same statements with sequential
// scans disabled show which index the planner WOULD use as the table grows.
DB::statement('SET enable_seqscan = off');
foreach (captured(static fn () => app(DayCardRepository::class)->forDay(PlanDayId::fromString((string) $day->id))) as $q) {
    explain('1b. forDay with enable_seqscan = off', $q);
}
foreach (captured(static fn () => app(LineAudioStore::class)->forScenes($scenes, $voices)) as $q) {
    explain('3b. forScenes with enable_seqscan = off', $q);
}
