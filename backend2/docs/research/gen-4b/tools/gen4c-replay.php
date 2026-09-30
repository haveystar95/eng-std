<?php

declare(strict_types=1);

/**
 * GEN-4c-2 · A DAY OF AN E2E PLAN BUILT AGAIN FROM ITS OWN ANSWERS — no call: the answers of the skeleton, the seam judge, the
 * repairs and the dialogue, read off the journal of the build (`e2e-bodies.php` → `calls-bodies.json`), handed back in their
 * order by the fake to the conveyor of the code as it is now (`LessonBuildService`), for the request the day was built for
 * (`LessonRequests::for` over the plan as the e2e database holds it — its earlier days, the learner's gender and own words). What
 * the build log says — every stage attempt with its findings and what was fatal, every repair (sent for what, kept or not, and
 * why), every read of the judge — is what the API path does not keep. The check counters are not written (a counter of this
 * replay is no counter of the stand). The skeleton the replay ends with is compared with the one the day stored.
 *
 * A repair is answered by what was recorded for its card (by address, in order), or given back as written when nothing was
 * recorded for it. The judge's recorded answers are read in the shape the code reads now (a list of the replies that name a
 * value → a verdict for each reply sent). With -e REPLAY_JUDGE=live -e PLAN_MODEL_DRIVER=openai the judge is asked anew —
 * the prompt of the code as it is now, paid, journaled — and everything else is the day's own answers.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test wt_gen4c php docs/research/gen-4b/tools/gen4c-replay.php \
 *       01M3QET095394QGAWYFS7K1JR6 2 docs/research/gen-4b/e2e-c/ro-day2
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test -e PLAN_MODEL_DRIVER=openai -e REPLAY_JUDGE=live -e REPLAY_OUT=replay-v1.3-1.json \
 *       wt_gen4c php docs/research/gen-4b/tools/gen4c-replay.php 01M3QET095394QGAWYFS7K1JR6 2 docs/research/gen-4b/e2e-c/ro-day2
 */

use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) DB::connection()->getDatabaseName() !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Refused: not wordtrainer_e2e_test.\n");
    exit(1);
}
[$planId, $number, $dir] = [(string) ($argv[1] ?? ''), (int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')];
$bodies = json_decode((string) file_get_contents("{$dir}/calls-bodies.json"), true, flags: JSON_THROW_ON_ERROR)['calls'];
$answers = ['skeleton' => [], 'dialogue' => [], 'repair' => [], 'seam_judge' => []];
foreach ($bodies as $call) {
    $prompt = (string) $call['prompt'];
    $kind = match (true) {
        str_starts_with($prompt, 'LESSON SKELETON') => 'skeleton',
        str_starts_with($prompt, 'LESSON DIALOGUE') => 'dialogue',
        str_starts_with($prompt, 'LESSON CARD REPAIR') => 'repair',
        str_starts_with($prompt, 'LESSON SEAM JUDGE') => 'seam_judge',
        default => null,
    };
    if ($kind !== null) {
        preg_match('/ADDRESS: (\S+)/', (string) $call['user'], $m);
        $answers[$kind][] = ['answer' => json_decode((string) $call['answer'], true, flags: JSON_THROW_ON_ERROR), 'address' => $m[1] ?? null];
    }
}

$live = getenv('REPLAY_JUDGE') === 'live';
// The real judge is taken before the fake is bound — the port the application builds from the config.
$real = $live ? app(PlanModelPort::class) : null;
if ($real instanceof FakePlanModel) {
    fwrite(STDERR, "Refused: a live judge needs -e PLAN_MODEL_DRIVER=openai.\n");
    exit(1);
}
$recorded = [];
foreach ($answers['repair'] as $one) {
    $recorded[(string) $one['address']][] = $one['answer'];
}
$judged = [];

$plan = app(PlanRepository::class)->findById(PlanId::fromString($planId)) ?? throw new RuntimeException("no plan {$planId}");
$scene = $plan->sceneOf($plan->day($number)) ?? throw new RuntimeException("day {$number} is no scene day");
$request = app(LessonRequests::class)->for($plan, $scene);

$asked = [];
// A stage asked more times than the day asked it gets its last recorded answer again — the replay goes on to the day's
// verdict (and its log) instead of stopping on a call nobody paid for. Said in the output (`reused`).
$reused = [];
$again = static function (string $stage, int $call) use ($answers, &$reused): array {
    if (isset($answers[$stage][$call - 1])) {
        return $answers[$stage][$call - 1]['answer'];
    }
    $reused[] = "{$stage} {$call}";

    return end($answers[$stage])['answer'] ?? throw new RuntimeException("no {$stage} answer");
};
$fake = new FakePlanModel(
    skeleton: static fn (LessonRequest $r, int $call): array => $again('skeleton', $call),
    dialogue: static fn (DialogueRequest $r, int $call): array => $again('dialogue', $call),
    repair: static function (LessonCardRepairRequest $r) use (&$recorded, &$asked): array {
        $answer = array_shift($recorded[$r->address]);
        $asked[] = ['address' => $r->address, 'recorded' => $answer !== null, 'findings' => $r->findings];

        return $answer ?? ['card' => $r->card];
    },
    judge: static function (NativeSeamJudgeRequest $r, int $call) use ($answers, $real, &$judged): array {
        if ($real !== null) {
            $reply = $real->judgeNativeSeams($r);
            $judged[] = ['prompt' => $reply->promptVersion, 'replies' => $r->replyIds(), 'answer' => $reply->payload, 'cost_usd' => $reply->costUsd];

            return $reply->payload;
        }
        // -e REPLAY_JUDGE_FROM=<a replay's output> — the judge's answers of that replay (a live one), in their order.
        $from = getenv('REPLAY_JUDGE_FROM');
        if ($from) {
            $earlier = json_decode((string) file_get_contents($from), true, flags: JSON_THROW_ON_ERROR)['judge_answers'][$call - 1] ?? throw new RuntimeException("no judge answer {$call} in {$from}");
            $judged[] = [...$earlier, 'cost_usd' => '0'];

            return $earlier['answer'];
        }
        $answer = $answers['seam_judge'][$call - 1]['answer'] ?? throw new RuntimeException("no judge answer {$call}");
        // v1.2 answered with the list of the replies that name a value: a verdict for every reply sent, as v1.3 answers.
        if (is_array($answer['replies_naming_values'] ?? null)) {
            $answer['replies'] = array_map(static fn (string $id): array => ['id' => $id, 'names_a_value' => in_array($id, $answer['replies_naming_values'], true)], $r->replyIds());
            unset($answer['replies_naming_values']);
        }
        $judged[] = ['prompt' => 'recorded', 'replies' => $r->replyIds(), 'answer' => $answer, 'cost_usd' => '0'];

        return $answer;
    },
);
app()->instance(PlanModelPort::class, $fake);
app()->instance(CheckCounters::class, new class implements CheckCounters
{
    public function recordCodes(string $promptVersion, array $codes, CheckAction $action = CheckAction::Counted): void {}

    public function record(string $promptVersion, array $findings): void {}

    public function all(): array
    {
        return [];
    }
});
app()->forgetInstance(LessonBuildService::class);

$outcome = app(LessonBuildService::class)->build($request);
$stored = json_decode((string) DB::table('plan_scenes')->where('id', $scene->id()->value)->value('skeleton_json'), true);
$replayed = $outcome->skeleton?->toArray();
$same = static fn (mixed $a, mixed $b): bool => json_encode(ksortDeep($a)) === json_encode(ksortDeep($b));

/** A value with every map's keys in order — jsonb keeps no order of keys. */
function ksortDeep(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    $value = array_map(ksortDeep(...), $value);
    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

$out = [
    'plan' => $planId,
    'day' => $number,
    'scene' => $scene->id()->value,
    'status' => $outcome->lesson !== null ? 'ready' : 'failed',
    'fail_reason' => $outcome->failReason,
    'same_skeleton_as_stored' => $replayed !== null && $same($replayed, $stored),
    'calls_replayed' => ['skeleton' => $fake->skeletonCalls, 'dialogue' => $fake->dialogueCalls, 'repair' => $fake->repairCalls, 'seam_judge' => $fake->judgeCalls],
    'calls_recorded' => array_map('count', $answers),
    'judge' => $live ? 'live' : (getenv('REPLAY_JUDGE_FROM') ? 'from '.basename((string) getenv('REPLAY_JUDGE_FROM')) : 'recorded'),
    'reused' => $reused,
    'repairs_asked' => $asked,
    'judge_answers' => $judged,
    'attempts' => $outcome->log->attempts,
    'repairs' => $outcome->log->repairs,
    'judgements' => $outcome->log->judgements,
    'findings_left' => $outcome->findings,
    'skeleton' => $replayed,
];
// -e REPLAY_FIXTURE=<file> — the day as a test replays it: the request (the story so far with it) and every answer the
// replay handed out, the judge's as it answered (a live judge — the version of the code as it is now).
if (getenv('REPLAY_FIXTURE')) {
    $fixture = [
        'about' => "Day {$number} of the e2e plan {$planId}: the request as LessonRequests put it together, the answers of its build",
        'request' => [
            'topic' => $request->topic, 'topic_description' => $request->topicDescription,
            'must_say' => $request->survival->mustSayColumn(), 'must_understand' => $request->survival->mustUnderstandColumn(),
            'target_language' => $request->targetLanguage, 'native_language' => $request->nativeLanguage,
            'target' => $request->targetLangCode, 'native' => $request->nativeLangCode, 'level' => $request->level->value,
            'learner_gender' => $request->learnerGender?->value, 'vocabulary' => [$request->vocabularyMin, $request->vocabularyMax],
            'roles' => [$request->roles->learnerTarget, $request->roles->learnerNative, $request->roles->partnerTarget, $request->roles->partnerNative],
            'earlier_days' => array_map(static fn ($d): array => [
                'number' => $d->number, 'title_target' => $d->titleTarget, 'partner_role_target' => $d->partnerRoleTarget,
                'partner_gender' => $d->partnerGender->value, 'lines' => $d->lines, 'frames' => $d->frames, 'words' => $d->words,
            ], $request->earlierDays->days),
            'scene_id' => $request->sceneId,
        ],
        'skeleton' => array_column($answers['skeleton'], 'answer'),
        'dialogue' => array_column($answers['dialogue'], 'answer'),
        'repairs' => array_map(static fn (array $one): array => ['address' => $one['address'], 'answer' => $one['answer']], $answers['repair']),
        'judge' => array_map(static fn (array $j): array => ['prompt' => $j['prompt'], 'answer' => $j['answer']], $judged),
    ];
    file_put_contents((string) getenv('REPLAY_FIXTURE'), json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
}
file_put_contents($dir.'/'.(getenv('REPLAY_OUT') ?: 'replay.json'), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, sprintf("replayed day %d of %s: %s%s, same skeleton as stored: %s, calls %s / recorded %s%s\n", $number, $planId, $out['status'], $out['fail_reason'] !== null ? " ({$out['fail_reason']})" : '', $out['same_skeleton_as_stored'] ? 'yes' : 'NO', json_encode($out['calls_replayed']), json_encode($out['calls_recorded']), $reused !== [] ? ', reused: '.implode(', ', $reused) : ''));
