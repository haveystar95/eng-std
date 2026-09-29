<?php

declare(strict_types=1);

/**
 * GEN-4 · THE E2E DAY (наряд GEN-4, §6). The plan «Собеседование в пятницу, боюсь вопросов про опыт», ru→ro, Beginner, two
 * scenes, built by the application as production builds it — `CreatePlan` → the plan call (`plan-builder-v2.1`, its checks,
 * the line repairs) → the day 1 in its two stages on the models of the config → the photos — for a QA learner of its own
 * (male, as the owner whose day 1 of 26.09 is the «было»), on `wordtrainer_e2e_test` and nothing else: the queue must be
 * `sync` and the model real. The voice is not bought here: this database is voiced only by name, after its price —
 * `plan:speak-backfill --plan=<id> --count`, then without `--count`.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test -e PLAN_MODEL_DRIVER=openai -e QUEUE_CONNECTION=sync wt_gen4 \
 *       php docs/research/gen-4b/tools/e2e.php build
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test wt_gen4 php docs/research/gen-4b/tools/e2e.php dump <plan-id>
 *
 * `dump` writes docs/research/gen-4b/e2e/: plan.json (the plan, its scenes with their survival sets, its findings),
 * day1-skeleton.json, day1-lesson.json (the lesson as stored — the shuffled options, the code's marks), day1-findings.json,
 * calls.json (the journal of model calls of the plan's build, and its money).
 */

use App\Modules\Identity\Application\Port\DevSignIn;
use App\Modules\Plan\Application\Command\CreatePlan;
use App\Modules\Plan\Application\Command\CreatePlanHandler;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const E2E_OUT = 'docs/research/gen-4b/e2e';
const GOAL = 'Собеседование в пятницу, боюсь вопросов про опыт';
const LEARNER = 'qa-gen4-ru-ro-0929@wt.test';

$database = (string) DB::connection()->getDatabaseName();
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Refused: the database is «{$database}», not wordtrainer_e2e_test.\n");
    exit(1);
}

/** @param array<string, mixed>|list<mixed>|null $data */
function e2eWrite(string $file, mixed $data): void
{
    if (! is_dir(E2E_OUT)) {
        mkdir(E2E_OUT, 0775, true);
    }
    file_put_contents(E2E_OUT."/{$file}", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
}

function jsonColumn(mixed $value): mixed
{
    return is_string($value) ? json_decode($value, true) : $value;
}

switch ($argv[1] ?? '') {
    case 'build':
        if (config('queue.default') !== 'sync' || config('plan.model.driver') === 'fake') {
            fwrite(STDERR, 'Refused: run with -e QUEUE_CONNECTION=sync -e PLAN_MODEL_DRIVER=openai (queue='.config('queue.default').', model='.config('plan.model.driver').").\n");
            exit(1);
        }
        if ((bool) config('generation.speech.enabled')) {
            fwrite(STDERR, "Refused: the voice is bought by name after its price, not here — run with -e SPEECH_ENABLED=false.\n");
            exit(1);
        }
        $auth = app(DevSignIn::class)->authenticate(LEARNER, 'gen4-e2e', 'Europe/Chisinau', 'ru');
        DB::table('profiles')->where('user_id', $auth->user->id)->update(['gender' => 'male']);
        $started = microtime(true);
        $id = app(CreatePlanHandler::class)(new CreatePlan(UserId::fromString($auth->user->id), GOAL, new LanguageCode('ro'), PlanLevel::Beginner, 2, null));
        fwrite(STDERR, sprintf("plan %s for %s built in %.0f s\n", $id->value, LEARNER, microtime(true) - $started));
        echo $id->value, "\n";
        break;

    case 'dump':
        $id = (string) ($argv[2] ?? '');
        $plan = DB::table('plans')->where('id', $id)->first();
        if ($plan === null) {
            fwrite(STDERR, "No plan {$id}\n");
            exit(1);
        }
        $scenes = DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->get();
        $days = DB::table('plan_days')->where('plan_id', $id)->orderBy('number')->get(['number', 'type', 'scene_id', 'status']);
        e2eWrite('plan.json', [
            'plan' => [
                'id' => $plan->id, 'status' => $plan->status, 'goal' => $plan->goal_text ?? null, 'title_native' => $plan->title_native,
                'title_target' => $plan->title_target, 'overdue_native' => $plan->overdue_native, 'prompt_version_plan' => $plan->prompt_version_plan,
                'model_plan' => $plan->model_plan, 'cost_usd_plan' => $plan->cost_usd_plan, 'attempts_plan' => $plan->attempts_plan,
                'findings' => jsonColumn($plan->checks_json),
            ],
            'days' => $days,
            'scenes' => $scenes->map(static fn (object $s): array => [
                'id' => $s->id, 'order' => $s->order, 'priority' => $s->priority, 'title_native' => $s->title_native, 'title_target' => $s->title_target,
                'teaches_native' => $s->teaches_native, 'goals_native' => jsonColumn($s->goals_native), 'topic_description' => $s->topic_description,
                'must_say' => jsonColumn($s->must_say), 'must_understand' => jsonColumn($s->must_understand), 'lesson_status' => $s->lesson_status,
                'fail_reason' => $s->fail_reason, 'prompt_version_lesson' => $s->prompt_version_lesson, 'model_lesson' => $s->model_lesson,
                'cost_usd_lesson' => $s->cost_usd_lesson, 'latency_ms_lesson' => $s->latency_ms_lesson, 'attempts_lesson' => $s->attempts_lesson,
            ])->all(),
        ]);
        $day1 = $days->firstWhere('number', 1);
        $scene = $scenes->firstWhere('id', $day1?->scene_id);
        if ($scene !== null) {
            e2eWrite('day1-skeleton.json', jsonColumn($scene->skeleton_json));
            e2eWrite('day1-lesson.json', jsonColumn($scene->lesson_json));
            e2eWrite('day1-findings.json', jsonColumn($scene->checks_json));
        }
        $from = $plan->created_at;
        $to = DB::table('plan_scenes')->where('plan_id', $id)->max('updated_at');
        $calls = DB::table('model_calls')->where('started_at', '>=', $from)->where('started_at', '<=', $to)->orderBy('started_at')
            ->get(['id', 'status', 'model', 'answered_model', 'purpose', 'tokens_in', 'cached_tokens', 'tokens_out', 'cost_usd', 'latency_ms', 'started_at']);
        e2eWrite('calls.json', ['calls' => $calls, 'cost_usd' => round((float) $calls->sum('cost_usd'), 6)]);
        fwrite(STDERR, sprintf("dumped plan %s: %d scenes, day 1 %s, %d calls, $%.4f\n", $id, $scenes->count(), $scene->lesson_status ?? '—', $calls->count(), (float) $calls->sum('cost_usd')));
        break;

    default:
        fwrite(STDERR, "e2e.php build|dump <plan-id>\n");
        exit(1);
}
