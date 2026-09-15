<?php

declare(strict_types=1);

/**
 * GEN-2b · EXPLAIN FOR WHAT THE НАРЯД ADDS TO THE DATABASE — no new table, column or read path: a check that did not
 * run for want of a language pack, a judged native seam and a judge that did not answer are three more names in the
 * counters' upsert (`CheckCounters::recordCodes`), and the stored findings a manual repair keeps are read off the scene
 * the command already loads. So: the upsert's plan for the new names (statement plans only — nothing is written) and
 * the admin read of the counters, on the database the live days wrote their counters to.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/explain.php > docs/research/gen-2b/explain.txt
 */

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) config('database.connections.pgsql.database') === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}

echo 'plan_check_counters rows: '.DB::table('plan_check_counters')->count()."\n";
foreach (DB::select("select indexname, indexdef from pg_indexes where tablename = 'plan_check_counters' order by indexname") as $index) {
    echo "  {$index->indexname}: {$index->indexdef}\n";
}
echo "\n";

$upsert = 'INSERT INTO plan_check_counters (id, prompt_version, check_name, action, hits, updated_at) VALUES (?, ?, ?, ?, ?, ?) '
    .'ON CONFLICT (prompt_version, check_name, action) DO UPDATE SET hits = plan_check_counters.hits + EXCLUDED.hits, updated_at = EXCLUDED.updated_at';
foreach ([LessonCodes::LANG_PACK_MISSING, LessonCodes::FILLER_NATIVE_SEAM, LessonCodes::JUDGE_UNAVAILABLE] as $name) {
    echo "=== counters upsert — {$name} (EXPLAIN, not executed)\n";
    foreach (DB::select('EXPLAIN (FORMAT TEXT) '.$upsert, [Ulid::generate(), 'lesson_day.v4.5', $name, 'counted', 1, now()]) as $row) {
        echo '    '.trim((string) $row->{'QUERY PLAN'})."\n";
    }
    echo "\n";
}

echo "=== admin: GET /admin/api/plans/checks — every counter, ordered (EXPLAIN ANALYZE)\n";
foreach (DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) select * from plan_check_counters order by prompt_version asc, check_name asc, action asc') as $row) {
    echo '    '.trim((string) $row->{'QUERY PLAN'})."\n";
}
