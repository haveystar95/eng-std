<?php

declare(strict_types=1);

/**
 * GEN-4 · THE GATE RUN (наряд GEN-4, §5). The seventeen goals of GEN-4a are planned by `plan-builder-v2.1` on `gpt-5.4`
 * through the plan's own build (`PlanBuildService`: its checks, its one retry, the line repairs) — the model's first answer
 * replayed from GEN-4a's gate run of the very same prompt and user message ({@see ReplayPlanModel}; `--live` asks anew),
 * the rest live; then the day 1 of every
 * plan's core scene is built by the day's own conveyor (`LessonBuildService`: skeleton → SkeletonCheck → seam judge →
 * the skeleton's repairs → dialogue → DialogueCheck → shuffle → the dialogue's repairs → the lesson) twice — the two stages
 * on `gpt-5.6-luna` and on `gpt-5.4`; the repairs on the repair model of the config (`gpt-5.6-luna`), the seam judge on
 * its own (`gpt-5.4-mini`) — and once more the skeleton alone on `gpt-5.6-luna` with `reasoning_effort` high. The prompts
 * are the files of `current/`, never edited between runs.
 *
 * Every model call goes through {@see RecordingPlanModel}, around the real `ContentModelPlanBuilder`: what was asked (the
 * user message; the system text is the prompt file's, named by its version), what came back (the parsed answer, the
 * content as the model wrote it, and the vendor's whole body over the wire — usage with the reasoning tokens), the model
 * that answered, the tokens, the price by `ModelCost`, the time. Nothing of the application is written — no plan, no scene:
 * only the stand's own journal of model calls and its check counters, in a database that must be a `wordtrainer_gen4*` one
 * (the worktree's `.env` names the live one; the container's env is what points elsewhere, and this script refuses to run
 * on anything else). No voice, no photos, no translation: nothing here calls them.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_gen4 wt_gen4 php docs/research/gen-4b/tools/gate.php plans [--only=01,02] [--live]
 *   docker exec -e DB_DATABASE=wordtrainer_gen4 wt_gen4 php docs/research/gen-4b/tools/gate.php days --run=luna|gpt54 [--only=]
 *   docker exec -e DB_DATABASE=wordtrainer_gen4 wt_gen4 php docs/research/gen-4b/tools/gate.php skeletons --run=luna-high [--only=]
 *   docker exec -e DB_DATABASE=wordtrainer_gen4 wt_gen4 php docs/research/gen-4b/tools/gate.php recheck   (no model call)
 *   docker exec wt_gen4 php docs/research/gen-4b/tools/gate.php table                  (no model call, no database)
 *
 * Writes into docs/research/gen-4b/runs/: `plans/<nn>.json` (the plan's build: inputs, the outcome, the survival sets, every
 * call), `days/<run>/<nn>.json` (the day's build: the request, every attempt with its findings, the repairs, the judge, the
 * skeleton, the lesson, every call), `skeletons/<run>/<nn>.json`; `recheck` — `recheck.json`, every stage answer of every run
 * read again by the checks of the code as it is now (one measure for runs made before and after a check changed); `table`
 * writes `summary.md`. MONEY: every call is appended
 * to `spend.json`; before a plan, a day or a skeleton, what is spent plus the dearest it may cost must fit under the cap —
 * {@see CAP_USD}, the order's $5 less the e2e's share — or it is skipped and said so.
 */

use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Application\Service\LessonSeamJudge;
use App\Modules\Plan\Application\Service\PlanBuildService;
use App\Modules\Plan\Application\Service\PlanLineRepairer;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Model\PlanModelChoice;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const OUT = 'docs/research/gen-4b';
const RUNS = OUT.'/runs';
const SPEND_FILE = OUT.'/spend.json';

/** Where `recheck` writes and `table` reads the one measure — `RECHECK_FILE` names another (GEN-4b: `recheck-b.json`). */
define('RECHECK_FILE', RUNS.'/'.(getenv('RECHECK_FILE') ?: 'recheck.json'));

/**
 * The caps on OpenAI, one `spend.json` for both: GEN-4's $5 (spent $4.9341, the e2e of §6 in it — unit `e2e`, its calls read
 * off the e2e journal) and GEN-4b's $1 on top — the three failed days again and its e2e (unit `e2e-b`).
 */
const CAP_USD = 5.9341;

/** The dearest one unit of each kind may cost, seen or feared: a plan, a day of either model, a skeleton thought hard about. */
const WORST_USD = ['plan' => 0.08, 'luna' => 0.25, 'gpt54' => 0.30, 'gpt54-b' => 0.30, 'luna-high' => 0.10];

/** The runs: which model writes the two stages, and with what effort. The repairs and the judge are the config's. */
const DAY_RUNS = [
    'luna' => ['model' => 'gpt-5.6-luna', 'effort' => null],
    'gpt54' => ['model' => 'gpt-5.4', 'effort' => null],
    // GEN-4b: the three days gpt-5.4 failed (02, 05, 14) again, on the prompts v1.1 and the checks of GEN-4b.
    'gpt54-b' => ['model' => 'gpt-5.4', 'effort' => null],
];
const SKELETON_RUNS = [
    'luna-high' => ['model' => 'gpt-5.6-luna', 'effort' => 'high'],
];

/**
 * THE SEVENTEEN GOALS OF GEN-4a (`tools/gen4/plan-prompt-run.php` of the branch `gen-4-prompts`, read only), as they were
 * tried there: every target of the seven and every native of the ten at least once, both levels; 16 continues 02 after its
 * three first scenes; 17 is meant to be unclear.
 */
const PLANS = [
    '01' => ['native' => 'ru', 'target' => 'ro', 'level' => 'beginner', 'scenes' => 2, 'goal' => 'Собеседование в пятницу, боюсь вопросов про опыт'],
    '02' => ['native' => 'ru', 'target' => 'en', 'level' => 'beginner', 'scenes' => 5, 'goal' => 'Иду к врачу с ребёнком, болит спина. Первый раз в местной клинике, боюсь не понять назначения'],
    '03' => ['native' => 'pl', 'target' => 'en', 'level' => 'intermediate', 'scenes' => 3, 'goal' => 'wynajem mieszkania, oglądanie z agentem, mam małego psa'],
    '04' => ['native' => 'ru', 'target' => 'de', 'level' => 'beginner', 'scenes' => 2, 'goal' => 'открыть счёт в банке, первый раз, нет постоянной регистрации'],
    '05' => ['native' => 'es', 'target' => 'en', 'level' => 'beginner', 'scenes' => 3, 'goal' => 'vuelo a Londres, primera vez, miedo al control de pasaportes'],
    '06' => ['native' => 'uk', 'target' => 'en', 'level' => 'intermediate', 'scenes' => 3, 'goal' => 'співбесіда на позицію менеджера, питання про зарплату'],
    '07' => ['native' => 'ru', 'target' => 'it', 'level' => 'intermediate', 'scenes' => 2, 'goal' => 'школа: встреча с учительницей сына, проблемы с поведением'],
    '08' => ['native' => 'ru', 'target' => 'en', 'level' => 'beginner', 'scenes' => 1, 'goal' => 'врач'],
    '09' => ['native' => 'be', 'target' => 'pl', 'level' => 'beginner', 'scenes' => 2, 'goal' => 'візіт да стаматолага, баліць зуб, першы раз у Польшчы'],
    '10' => ['native' => 'ro', 'target' => 'fr', 'level' => 'intermediate', 'scenes' => 2, 'goal' => 'interviu pentru un job în Franța, prima dată, emoții la întrebări despre experiență'],
    '11' => ['native' => 'de', 'target' => 'es', 'level' => 'beginner', 'scenes' => 3, 'goal' => 'Wohnung mieten in Madrid, Besichtigung mit dem Makler, kleines Kind'],
    '12' => ['native' => 'fr', 'target' => 'en', 'level' => 'beginner', 'scenes' => 2, 'goal' => 'rendez-vous à la banque pour ouvrir un compte, sans adresse permanente'],
    '13' => ['native' => 'it', 'target' => 'en', 'level' => 'intermediate', 'scenes' => 2, 'goal' => 'colloquio di lavoro, domande sullo stipendio'],
    '14' => ['native' => 'en', 'target' => 'ro', 'level' => 'beginner', 'scenes' => 2, 'goal' => 'Job interview on Friday, worried about questions on my experience'],
    '15' => ['native' => 'en', 'target' => 'de', 'level' => 'intermediate', 'scenes' => 3, 'goal' => 'Renting a flat in Berlin, viewing with the agent, I have a small dog'],
    '16' => ['native' => 'ru', 'target' => 'en', 'level' => 'beginner', 'scenes' => 2, 'goal' => 'Иду к врачу с ребёнком, болит спина. Первый раз в местной клинике, боюсь не понять назначения', 'continues' => '02', 'existing' => 3],
    '17' => ['native' => 'ru', 'target' => 'en', 'level' => 'beginner', 'scenes' => 3, 'goal' => 'хочу подтянуть английский'],
];

const JSON_OUT = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

/**
 * Every call of the plan's model, recorded around the real builder — the answer as parsed, the content as written, the
 * vendor's body over the wire (usage with the reasoning tokens), the model, the tokens, the price, the time.
 */
final class RecordingPlanModel implements PlanModelPort
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    /** The vendor's last body over the wire, set by the listener of the HTTP client. */
    public static ?string $wire = null;

    private PlanPromptFiles $prompts;

    public function __construct(private readonly PlanModelPort $inner, private readonly string $unit)
    {
        $this->prompts = new PlanPromptFiles;
    }

    public function buildPlan(PlanRequest $request): ModelReply
    {
        return $this->record('plan', fn (): ModelReply => $this->inner->buildPlan($request), $this->prompts->planUser($request));
    }

    public function repairPlanLine(PlanLineRepairRequest $request): ModelReply
    {
        return $this->record('plan_line_repair', fn (): ModelReply => $this->inner->repairPlanLine($request), $this->prompts->planLineUser($request));
    }

    public function buildSkeleton(LessonRequest $request): ModelReply
    {
        return $this->record('skeleton', fn (): ModelReply => $this->inner->buildSkeleton($request), $this->prompts->skeletonUser($request));
    }

    public function buildDialogue(DialogueRequest $request): ModelReply
    {
        return $this->record('dialogue', fn (): ModelReply => $this->inner->buildDialogue($request), $this->prompts->dialogueUser($request));
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->record('repair', fn (): ModelReply => $this->inner->repairLessonCard($request), $this->prompts->repairUser($request), ['address' => $request->address, 'kind' => $request->kind]);
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        return $this->record('seam_judge', fn (): ModelReply => $this->inner->judgeNativeSeams($request), $this->prompts->judgeUser($request));
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        throw new LogicException('the gate run judges no slot');
    }

    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        throw new LogicException('the gate run has no talk');
    }

    public function planPromptVersion(): string
    {
        return $this->inner->planPromptVersion();
    }

    public function skeletonPromptVersion(): string
    {
        return $this->inner->skeletonPromptVersion();
    }

    public function dialoguePromptVersion(): string
    {
        return $this->inner->dialoguePromptVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->inner->lessonPromptVersion();
    }

    public function repairPromptVersion(): string
    {
        return $this->inner->repairPromptVersion();
    }

    public function judgePromptVersion(): string
    {
        return $this->inner->judgePromptVersion();
    }

    public function slotJudgePromptVersion(): string
    {
        return $this->inner->slotJudgePromptVersion();
    }

    public function conversationPromptVersion(): string
    {
        return $this->inner->conversationPromptVersion();
    }

    /** @param array<string, mixed> $about */
    private function record(string $purpose, Closure $call, string $user, array $about = []): ModelReply
    {
        self::$wire = null;
        $at = gmdate('Y-m-d\TH:i:s\Z');
        $started = hrtime(true);
        try {
            /** @var ModelReply $reply */
            $reply = $call();
        } catch (Throwable $e) {
            $row = ['purpose' => $purpose, 'at' => $at, 'user' => $user, 'error' => $e->getMessage(), 'wire' => self::$wire, 'wall_ms' => intdiv(hrtime(true) - $started, 1_000_000)] + $about;
            $this->calls[] = $row;
            spend(['at' => $at, 'unit' => $this->unit, 'purpose' => $purpose, 'model' => null, 'cost_usd' => '0.000000', 'error' => $e->getMessage()]);
            throw $e;
        }
        $envelope = is_string(self::$wire) ? json_decode(self::$wire, true) : null;
        // The reply's own `raw` is cut at 4 000 characters for the journal; the content as written is the wire's.
        $content = is_array($envelope) ? ($envelope['choices'][0]['message']['content'] ?? null) : null;
        $this->calls[] = [
            'purpose' => $purpose,
            'at' => $at,
            'prompt_version' => $reply->promptVersion,
            'model' => $reply->model,
            'tokens_in' => $reply->tokensIn,
            'cached_tokens_in' => $reply->cachedTokensIn,
            'tokens_out' => $reply->tokensOut,
            'reasoning_tokens' => is_array($envelope) ? ($envelope['usage']['completion_tokens_details']['reasoning_tokens'] ?? null) : null,
            'cost_usd' => $reply->costUsd,
            'latency_ms' => $reply->latencyMs,
            'wall_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'call_id' => $reply->callId,
            'user' => $user,
            'payload' => $reply->payload,
            'raw' => is_string($content) ? $content : $reply->raw,
            'wire' => $envelope ?? self::$wire,
        ] + $about;
        spend(['at' => $at, 'unit' => $this->unit, 'purpose' => $purpose, 'model' => $reply->model, 'tokens_in' => $reply->tokensIn, 'cached_tokens_in' => $reply->cachedTokensIn, 'tokens_out' => $reply->tokensOut, 'cost_usd' => $reply->costUsd]);

        return $reply;
    }
}

/**
 * THE PLAN'S ANSWER REPLAYED: the first plan call of a goal is answered by the answer `gpt-5.4` gave GEN-4a's gate run of the
 * same prompt (`plan-builder-v2.1`, version 8 — the file's sha256 and the system text's are the same, and so is the user
 * message: `runs/plans/gen4a/<nn>-<native>-<target>.json`, copied from the branch `gen-4-prompts`, read only). Everything
 * after it is live: the plan's checks, a retry if they refuse it (a real call), the line repairs. What GEN-4a paid for the
 * answer is written beside the call and is not this run's money.
 */
final class ReplayPlanModel implements PlanModelPort
{
    private bool $replayed = false;

    public function __construct(private readonly PlanModelPort $inner, private readonly string $stem) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        if ($this->replayed) {
            return $this->inner->buildPlan($request);
        }
        $this->replayed = true;
        $content = (string) file_get_contents(RUNS."/plans/gen4a/{$this->stem}.json");
        $call = json_decode((string) file_get_contents(RUNS."/plans/gen4a/{$this->stem}.call.json"), true);

        return new ModelReply(
            payload: json_decode($content, true, flags: JSON_THROW_ON_ERROR),
            promptVersion: 'plan-builder-v2.1',
            model: (string) ($call['answered_model'] ?? 'gpt-5.4'),
            tokensIn: $call['tokens_in'] ?? null,
            tokensOut: $call['tokens_out'] ?? null,
            costUsd: '0.000000',
            latencyMs: (int) ($call['latency_ms'] ?? 0),
            raw: $content,
            cachedTokensIn: $call['cached_tokens_in'] ?? null,
        );
    }

    public function repairPlanLine(PlanLineRepairRequest $request): ModelReply
    {
        return $this->inner->repairPlanLine($request);
    }

    public function buildSkeleton(LessonRequest $request): ModelReply
    {
        return $this->inner->buildSkeleton($request);
    }

    public function buildDialogue(DialogueRequest $request): ModelReply
    {
        return $this->inner->buildDialogue($request);
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->inner->repairLessonCard($request);
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        return $this->inner->judgeNativeSeams($request);
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        return $this->inner->judgeSlot($request);
    }

    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        return $this->inner->conversationTurn($request);
    }

    public function planPromptVersion(): string
    {
        return $this->inner->planPromptVersion();
    }

    public function skeletonPromptVersion(): string
    {
        return $this->inner->skeletonPromptVersion();
    }

    public function dialoguePromptVersion(): string
    {
        return $this->inner->dialoguePromptVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->inner->lessonPromptVersion();
    }

    public function repairPromptVersion(): string
    {
        return $this->inner->repairPromptVersion();
    }

    public function judgePromptVersion(): string
    {
        return $this->inner->judgePromptVersion();
    }

    public function slotJudgePromptVersion(): string
    {
        return $this->inner->slotJudgePromptVersion();
    }

    public function conversationPromptVersion(): string
    {
        return $this->inner->conversationPromptVersion();
    }
}

/** @param array<string, mixed> $row */
function spend(array $row): void
{
    $rows = is_file(SPEND_FILE) ? (json_decode((string) file_get_contents(SPEND_FILE), true) ?: []) : [];
    $rows[] = $row;
    file_put_contents(SPEND_FILE, json_encode($rows, JSON_OUT)."\n");
}

function spent(): float
{
    $rows = is_file(SPEND_FILE) ? (json_decode((string) file_get_contents(SPEND_FILE), true) ?: []) : [];

    return array_sum(array_map(static fn (array $r): float => (float) ($r['cost_usd'] ?? 0), $rows));
}

/** May a unit that may cost `$worst` still be paid for? */
function affordable(string $what, float $worst): bool
{
    if (spent() + $worst > CAP_USD) {
        fwrite(STDERR, sprintf("%s SKIPPED: spent $%.4f, it may cost $%.2f, the cap is $%.2f\n", $what, spent(), $worst, CAP_USD));

        return false;
    }

    return true;
}

/**
 * A builder on the real catalogue with the models of a run: the config's for every purpose but the ones named.
 *
 * @param  array<string, PlanModelChoice>  $override
 */
function builder(array $override = []): ContentModelPlanBuilder
{
    $choices = [];
    foreach ((array) config('plan.model.purposes', []) as $purpose => $row) {
        $effort = is_array($row) ? ($row['reasoning_effort'] ?? null) : null;
        $choices[(string) $purpose] = new PlanModelChoice((string) ($row['model'] ?? 'gpt-5.4'), is_string($effort) && $effort !== '' ? $effort : null);
    }

    return new ContentModelPlanBuilder(
        app(ContentModelCatalog::class),
        new PlanPromptFiles,
        ProviderId::OpenAi,
        [...$choices, ...$override],
        (int) config('plan.model.plan_timeout', 180),
        (int) config('plan.model.lesson_timeout', 180),
    );
}

/** The application's services over one recorder: nothing the container had made of them before is reused. */
function bindModel(RecordingPlanModel $model): void
{
    app()->instance(PlanModelPort::class, $model);
    foreach ([PlanBuildService::class, PlanLineRepairer::class, LessonBuildService::class, LessonSeamJudge::class, LessonCardRepairer::class] as $class) {
        app()->forgetInstance($class);
    }
}

/** @return list<string> */
function only(array $argv): array
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--only=')) {
            return array_values(array_filter(explode(',', substr($arg, 7))));
        }
    }

    return array_map(static fn (int|string $id): string => str_pad((string) $id, 2, '0', STR_PAD_LEFT), array_keys(PLANS));
}

function option(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return null;
}

function write(string $path, mixed $data): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, json_encode($data, JSON_OUT)."\n");
}

/** @return array<string, mixed>|null */
function readJson(string $path): ?array
{
    return is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
}

/** @return array<string, mixed> a scene as the gate run reads it */
function sceneRow(SceneBrief $scene): array
{
    return [
        'order' => $scene->order,
        'kind' => $scene->kind->value,
        'priority' => $scene->priority,
        'title_native' => $scene->titleNative,
        'title_target' => $scene->titleTarget,
        'teaches_native' => $scene->teachesNative,
        'goals_native' => $scene->goalsNative,
        'learner_role' => [$scene->learnerRoleTarget, $scene->learnerRoleNative],
        'partner_role' => [$scene->partnerRoleTarget, $scene->partnerRoleNative],
        'topic_description' => $scene->topicDescription,
        'must_say' => $scene->survival->sayLines(),
        'must_understand' => $scene->survival->mustUnderstand,
    ];
}

/**
 * The scene whose day 1 the gate run builds: the plan's core (priority 1); of a continuation, its first new scene by
 * priority. Null for a plan with no scene (unclear, failed).
 *
 * @return array<string, mixed>|null
 */
function coreScene(array $plan): ?array
{
    $scenes = $plan['outcome']['scenes'] ?? [];
    if ($scenes === []) {
        return null;
    }
    usort($scenes, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

    return $scenes[0];
}

/** The day 1 of a plan's core scene, asked as `LessonRequests` asks it — the learner's gender unknown, no earlier day. */
function dayRequest(string $id, array $plan): ?LessonRequest
{
    $scene = coreScene($plan);
    if ($scene === null) {
        return null;
    }
    $goal = PLANS[$id];
    $level = PlanLevel::from($goal['level']);
    [$min, $max] = app(PlanConfig::class)->vocabularyRange($level);
    $survival = App\Modules\Plan\Domain\Blueprint\SurvivalSet::fromModel($scene['must_say'], $scene['must_understand']);
    $learner = $plan['outcome']['plan']['learner_role'] ?? $scene['learner_role'];

    return new LessonRequest(
        topic: $scene['title_native'],
        topicDescription: LessonRequests::topicDescription($scene['topic_description'], $goal['goal']),
        survival: $survival,
        targetLanguage: LanguageName::of($goal['target']),
        nativeLanguage: LanguageName::of($goal['native']),
        level: $level,
        learnerGender: null,
        vocabularyMin: $min,
        vocabularyMax: $max,
        roles: new LessonRoles($learner[0], $learner[1], $scene['partner_role'][0], $scene['partner_role'][1]),
        earlierDays: new EarlierDays,
        targetLangCode: $goal['target'],
        nativeLangCode: $goal['native'],
        sceneId: "gen4b-{$id}",
    );
}

/** @param list<LessonViolation> $found @return list<array{code: string, address: string, detail: string, fatal: bool}> */
function rows(array $found): array
{
    return array_map(static fn (LessonViolation $v): array => [...$v->toArray(), 'fatal' => LessonCodes::isFatal($v->code)], $found);
}

$command = $argv[1] ?? '';
if ($command !== 'table') {
    $database = (string) DB::connection()->getDatabaseName();
    if (! str_starts_with($database, 'wordtrainer_gen4')) {
        fwrite(STDERR, "Refused: the database is «{$database}», not a stand's (wordtrainer_gen4*). Run with -e DB_DATABASE=wordtrainer_gen4.\n");
        exit(1);
    }
    config(['generation.speech.enabled' => false]);
    Event::listen(ResponseReceived::class, static function (ResponseReceived $event): void {
        if (str_contains($event->request->url(), '/chat/completions')) {
            RecordingPlanModel::$wire = $event->response->body();
        }
    });
    fwrite(STDERR, sprintf("database=%s cap=$%.2f spent=$%.4f\n%s", $database, CAP_USD, spent(), $command === 'recheck' ? '' : "NOTE: the REAL models — every call below is paid.\n"));
}

switch ($command) {
    case 'plans':
        foreach (only($argv) as $id) {
            $goal = PLANS[$id] ?? null;
            if ($goal === null || ! affordable("plan {$id}", WORST_USD['plan'])) {
                continue;
            }
            $existing = [];
            if (isset($goal['continues'])) {
                $from = readJson(RUNS."/plans/{$goal['continues']}.json");
                $scenes = $from['outcome']['scenes'] ?? [];
                usort($scenes, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
                foreach (array_slice($scenes, 0, $goal['existing']) as $scene) {
                    $existing[] = ['title' => $scene['title_native'], 'must_say' => $scene['must_say']];
                }
                if (count($existing) < $goal['existing']) {
                    fwrite(STDERR, "plan {$id} SKIPPED: plan {$goal['continues']} has no {$goal['existing']} scenes yet\n");

                    continue;
                }
            }
            $request = new PlanRequest($goal['goal'], LanguageName::of($goal['target']), LanguageName::of($goal['native']), PlanLevel::from($goal['level']), $goal['scenes'], $existing);
            $stem = "{$id}-{$goal['native']}-{$goal['target']}";
            $live = in_array('--live', $argv, true) || ! is_file(RUNS."/plans/gen4a/{$stem}.json");
            $model = new RecordingPlanModel($live ? builder() : new ReplayPlanModel(builder(), $stem), "plan-{$id}");
            bindModel($model);
            $started = hrtime(true);
            $error = null;
            $outcome = null;
            try {
                $outcome = app(PlanBuildService::class)->build($request);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
            $blueprint = $outcome?->blueprint;
            write(RUNS."/plans/{$id}.json", [
                'plan' => $id,
                'inputs' => $goal + ['existing_scenes' => $existing],
                'answer' => $live ? 'live' : "replayed: runs/plans/gen4a/{$stem}.json (GEN-4a paid $".(json_decode((string) file_get_contents(RUNS."/plans/gen4a/{$stem}.call.json"), true)['cost_usd'] ?? '?').')',
                'outcome' => [
                    'status' => $error !== null ? 'error' : ($blueprint !== null ? 'ok' : ($outcome?->unclearReason !== null ? 'unclear' : 'failed')),
                    'error' => $error,
                    'unclear_reason' => $outcome?->unclearReason,
                    'fail_reason' => $outcome?->failReason,
                    'findings' => $outcome?->findings ?? [],
                    'plan' => $blueprint?->titles === null ? null : [
                        'title_native' => $blueprint->titles->titleNative,
                        'title_target' => $blueprint->titles->titleTarget,
                        'overdue_native' => $blueprint->titles->overdueNative,
                        'learner_role' => [$blueprint->titles->learnerRoleTarget, $blueprint->titles->learnerRoleNative],
                    ],
                    'scenes' => $blueprint === null ? [] : array_map(sceneRow(...), $blueprint->scenes),
                    'cost_usd' => $outcome?->call?->costUsd,
                ],
                'wall_ms' => intdiv(hrtime(true) - $started, 1_000_000),
                'calls' => $model->calls,
            ]);
            fwrite(STDERR, sprintf("plan %s: %s, %d scenes, %d calls · spent $%.4f\n", $id, $error ?? ($blueprint !== null ? 'ok' : 'no plan'), count($blueprint->scenes ?? []), count($model->calls), spent()));
        }
        break;

    case 'days':
        $run = option($argv, 'run');
        if (! isset(DAY_RUNS[$run])) {
            fwrite(STDERR, 'days --run='.implode('|', array_keys(DAY_RUNS))."\n");
            exit(1);
        }
        $choice = new PlanModelChoice(DAY_RUNS[$run]['model'], DAY_RUNS[$run]['effort']);
        foreach (only($argv) as $id) {
            $plan = readJson(RUNS."/plans/{$id}.json");
            $request = $plan === null ? null : dayRequest($id, $plan);
            if ($request === null) {
                fwrite(STDERR, "day {$id} ({$run}) SKIPPED: no scene\n");

                continue;
            }
            if (! affordable("day {$id} ({$run})", WORST_USD[$run])) {
                continue;
            }
            $model = new RecordingPlanModel(builder([ContentModelPlanBuilder::SKELETON => $choice, ContentModelPlanBuilder::DIALOGUE => $choice]), "day-{$run}-{$id}");
            bindModel($model);
            $started = hrtime(true);
            $error = null;
            $outcome = null;
            try {
                $outcome = app(LessonBuildService::class)->build($request);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
            $cost = array_sum(array_map(static fn (array $c): float => (float) ($c['cost_usd'] ?? 0), $model->calls));
            write(RUNS."/days/{$run}/{$id}.json", [
                'plan' => $id,
                'run' => $run,
                'models' => ['stages' => DAY_RUNS[$run], 'repair' => config('plan.model.purposes.repair.model'), 'seam_judge' => config('plan.model.purposes.seam_judge.model')],
                'scene' => coreScene($plan),
                'request' => ['topic' => $request->topic, 'vocabulary' => [$request->vocabularyMin, $request->vocabularyMax], 'scene_id' => $request->sceneId],
                'outcome' => [
                    'status' => $error !== null ? 'error' : ($outcome?->lesson !== null ? 'ok' : 'failed'),
                    'error' => $error,
                    'fail_reason' => $outcome?->failReason,
                    'findings' => $outcome?->findings ?? [],
                    'attempts' => $outcome?->log->attempts ?? [],
                    'repairs' => $outcome?->log->repairs ?? [],
                    'judgements' => $outcome?->log->judgements ?? [],
                    'skeleton' => $outcome?->skeleton?->toArray(),
                    'lesson' => $outcome?->lesson?->toArray(),
                ],
                'cost_usd' => round($cost, 6),
                'latency_ms' => array_sum(array_map(static fn (array $c): int => (int) ($c['latency_ms'] ?? 0), $model->calls)),
                'wall_ms' => intdiv(hrtime(true) - $started, 1_000_000),
                'calls' => $model->calls,
            ]);
            fwrite(STDERR, sprintf("day %s (%s): %s, %d calls, $%.4f · spent $%.4f\n", $id, $run, $error ?? ($outcome?->failReason ?? 'ok'), count($model->calls), $cost, spent()));
        }
        break;

    case 'skeletons':
        $run = option($argv, 'run');
        if (! isset(SKELETON_RUNS[$run])) {
            fwrite(STDERR, 'skeletons --run='.implode('|', array_keys(SKELETON_RUNS))."\n");
            exit(1);
        }
        $choice = new PlanModelChoice(SKELETON_RUNS[$run]['model'], SKELETON_RUNS[$run]['effort']);
        foreach (only($argv) as $id) {
            $plan = readJson(RUNS."/plans/{$id}.json");
            $request = $plan === null ? null : dayRequest($id, $plan);
            if ($request === null || ! affordable("skeleton {$id} ({$run})", WORST_USD[$run])) {
                continue;
            }
            $model = new RecordingPlanModel(builder([ContentModelPlanBuilder::SKELETON => $choice]), "skeleton-{$run}-{$id}");
            $started = hrtime(true);
            $error = null;
            $found = null;
            try {
                $reply = $model->buildSkeleton($request);
                try {
                    $skeleton = (new LessonParser)->skeleton($reply->payload);
                    $found = rows((new SkeletonCheck)->run($skeleton, app(LessonContexts::class)->skeleton($request)));
                } catch (ModelAnswerOffSchema $e) {
                    $error = 'off schema: '.$e->getMessage();
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
            write(RUNS."/skeletons/{$run}/{$id}.json", [
                'plan' => $id,
                'run' => $run,
                'model' => SKELETON_RUNS[$run],
                'scene' => coreScene($plan),
                'error' => $error,
                'findings' => $found,
                'cost_usd' => round(array_sum(array_map(static fn (array $c): float => (float) ($c['cost_usd'] ?? 0), $model->calls)), 6),
                'wall_ms' => intdiv(hrtime(true) - $started, 1_000_000),
                'calls' => $model->calls,
            ]);
            fwrite(STDERR, sprintf("skeleton %s (%s): %s, %d findings · spent $%.4f\n", $id, $run, $error ?? 'ok', count($found ?? []), spent()));
        }
        break;

    case 'recheck':
        // ONE MEASURE for every run: each stage answer recorded — skeletons and dialogues, first and repeated, of every run —
        // read again by the checks of the code as it is now, no call. A run made before a check changed is measured by the
        // same rules as the one made after it (the dialogue against the skeleton its request carried).
        $contexts = app(LessonContexts::class);
        $parser = new LessonParser;
        $out = ['checked_at' => gmdate('Y-m-d\TH:i:s\Z'), 'runs' => []];
        foreach ([...array_map(static fn (string $r): string => "days/{$r}", array_keys(DAY_RUNS)), ...array_map(static fn (string $r): string => "skeletons/{$r}", array_keys(SKELETON_RUNS))] as $dir) {
            foreach (glob(RUNS."/{$dir}/*.json") ?: [] as $file) {
                $row = readJson($file);
                $id = (string) $row['plan'];
                $request = dayRequest($id, (array) readJson(RUNS."/plans/{$id}.json"));
                if ($request === null) {
                    continue;
                }
                $answers = ['skeleton' => [], 'dialogue' => []];
                foreach ($row['calls'] as $call) {
                    $stage = $call['purpose'];
                    if (! isset($answers[$stage]) || ! array_key_exists('payload', $call)) {
                        continue;
                    }
                    $entry = ['attempt' => count($answers[$stage]) + 1, 'off_schema' => null, 'findings' => []];
                    try {
                        if ($stage === 'skeleton') {
                            $entry['findings'] = rows((new SkeletonCheck)->run($parser->skeleton((array) $call['payload']), $contexts->skeleton($request)));
                        } else {
                            $user = (string) $call['user'];
                            $from = strpos($user, "SKELETON:\n");
                            $to = $from === false ? false : strpos($user, "\n}", $from);
                            $given = $to === false ? null : json_decode(substr($user, $from + 10, $to + 2 - $from - 10), true);
                            if (! is_array($given)) {
                                throw new LogicException("{$dir}/{$id}: no skeleton in the dialogue's request");
                            }
                            $skeleton = $parser->skeleton($given);
                            $entry['findings'] = rows((new DialogueCheck)->run($parser->dialogue((array) $call['payload']), $contexts->dialogue($request, $skeleton)));
                        }
                    } catch (ModelAnswerOffSchema $e) {
                        $entry['off_schema'] = $e->getMessage();
                    }
                    $answers[$stage][] = $entry;
                }
                $out['runs'][$dir][$id] = $answers;
            }
        }
        write(RECHECK_FILE, $out);
        fwrite(STDERR, 'written '.RECHECK_FILE."\n");
        break;

    case 'table':
        require __DIR__.'/table.php';
        break;

    default:
        fwrite(STDERR, "gate.php plans|days|skeletons|recheck|table\n");
        exit(1);
}
