<?php

declare(strict_types=1);

/**
 * GEN-4 · THE OPTIONS A LEARNER IS DEALT, BEFORE AND AFTER (наряд GEN-4, 3.7; the invariant review of the branch). Until
 * GEN-4 the served lesson shuffled the options of a lesson written in one call at every reading; since GEN-4 it moves
 * nothing, and the migration `2026_09_29_100300_shuffle_options_of_one_call_lessons` shuffles each such stored lesson once.
 * This prints, for every scene with a lesson, the options of every check and listening question in the order the SERVED
 * lesson has them — run once by the code of main against a database, once by the code of the branch against a copy of it
 * that the migration has run on, and the two outputs must be the same, option for option.
 *
 *   docker exec -i -w /app -e DB_DATABASE=wordtrainer_e2e_test wt_gen4 php < docs/research/gen-4b/tools/served-options.php > before.json
 *   docker exec -i -w /wt  -e DB_DATABASE=<the migrated copy>  wt_gen4 php < docs/research/gen-4b/tools/served-options.php > after.json
 *
 * Read only: the session is READ ONLY before anything is read.
 */

use App\Modules\Plan\Infrastructure\Eloquent\PlanMapper;
use App\Modules\Plan\Infrastructure\Eloquent\PlanModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
fwrite(STDERR, 'code='.getcwd().' database='.DB::connection()->getDatabaseName()."\n");

$mapper = app(PlanMapper::class);
$out = [];
$errors = 0;
PlanModel::query()->with(['scenes', 'days'])->orderBy('id')->chunk(50, static function ($rows) use ($mapper, &$out, &$errors): void {
    foreach ($rows as $row) {
        try {
            $plan = $mapper->toDomain($row);
        } catch (Throwable $e) {
            $errors++;
            fwrite(STDERR, "plan {$row->id}: {$e->getMessage()}\n");

            continue;
        }
        foreach ($plan->scenes() as $scene) {
            $lesson = $scene->lesson()?->toArray();
            if ($lesson === null) {
                continue;
            }
            $options = [];
            foreach ($lesson['dialogue'] as $exchange) {
                $options[] = [array_map(static fn (array $o): string => (string) ($o['text_target'] ?? ''), $exchange['check']['options']), $exchange['check']['correct_option_index']];
            }
            foreach ($lesson['listening']['questions'] ?? [] as $question) {
                $options[] = [$question['options_native'], $question['correct_option_index']];
            }
            $out[$scene->id()->value] = $options;
        }
    }
});
ksort($out);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
fwrite(STDERR, count($out)." served lessons, {$errors} plans unread\n");
