<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The plan's model calls over Generation's vendor seam: the prompt file is the system side, the inputs
 * are the user side, the schema is enforced by the vendor, and the request log labels the spend
 * `plan` — one budget — while the journal of model calls names each call for what it is: `plan`, `lesson`,
 * `repair`, `judge` (наряд BACK-TAILS-1 §3.3). Each call is built with the plan's own timeout, not the comparison
 * stack's.
 *
 * Six calls, each on its own model (`config/plan.php`): the plan, the lesson and the repair of one card on the
 * strong model (a cheaper repair did not repair as well — report GEN-2b), the seam judge a step cheaper (a verdict
 * is a yes or a no), the slot judge on the seam judge's model — the one call a learner WAITS on (наряд
 * SESSION-1a, разд. 4), so it gets one attempt and its own short timeout, and says in the log what it cost — and,
 * since наряд CONV-1, the conversation agent: also synchronous, also one attempt, on a `mini` model because a turn
 * has six seconds to come back and a talk is a dozen of them.
 *
 * The rules go first and the same for every call (the prompt file as the system message, and a schema that names no id of
 * the day), the day's inputs after them (наряд GEN-3) — so the vendor serves the rules from its prompt cache; what it
 * served is on the reply ({@see ModelReply::$cachedTokensIn}) and in the journal of model calls.
 */
final readonly class ContentModelPlanBuilder implements PlanModelPort
{
    /** What the plan's spend is called in the request log — one budget, one label. */
    public const PURPOSE = 'plan';

    /**
     * What the JOURNAL of model calls calls each of them (наряд BACK-TAILS-1 §3.3). The money is one purpose; the
     * calls are four different things, and a lost row that said only «plan» could not be told from the others when
     * the vendor's invoice is read against it.
     */
    public const JOURNAL_PLAN = 'plan';

    public const JOURNAL_LESSON = 'lesson';

    public const JOURNAL_REPAIR = 'repair';

    public const JOURNAL_JUDGE = 'judge';

    /** The talk with the agent (наряд CONV-1) — its own name in the journal, its own model, its own timeout. */
    public const JOURNAL_CONVERSATION = 'conversation';

    /** The slot judge's attempts: one (D-28) — past its timeout the code rules, a retry would only lengthen the wait. */
    public const SLOT_JUDGE_ATTEMPTS = 1;

    /** Seconds of that one attempt when the caller names none — `plan.slot_judge.timeout`'s own default. */
    public const SLOT_JUDGE_TIMEOUT = 8;

    /** The agent's attempts: one. The learner is sitting through this wait; a retry doubles it. */
    public const CONVERSATION_ATTEMPTS = 1;

    /** Seconds of that one attempt when the caller names none — `plan.conversation.timeout`'s own default. */
    public const CONVERSATION_TIMEOUT = 20;

    public function __construct(
        private ContentModelCatalog $catalog,
        private PlanPromptFiles $prompts,
        private ProviderId $provider,
        private string $planModel,
        private string $lessonModel,
        private int $planTimeout,
        private int $lessonTimeout,
        private string $repairModel,
        private string $judgeModel,
        /** Seconds of the slot judge's one attempt — `plan.slot_judge.timeout`, threaded in by the provider. */
        private int $slotJudgeTimeout = self::SLOT_JUDGE_TIMEOUT,
        /** The agent of the talk — a `mini` class model: the learner waits for it (наряд CONV-1, п. 4). */
        private string $conversationModel = 'gpt-5.4-mini',
        private int $conversationTimeout = self::CONVERSATION_TIMEOUT,
    ) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        $model = $this->model($this->planModel, $this->planTimeout, journalPurpose: self::JOURNAL_PLAN);
        $text = $this->prompts->planSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->planVersion(), PromptShape::Full, hash('sha256', $text));

        return self::reply($model->complete($prompt, $this->prompts->planUser($request), PlanSchemas::plan()), $prompt->version);
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        $model = $this->model($this->lessonModel, $this->lessonTimeout, journalPurpose: self::JOURNAL_LESSON);
        $text = $this->prompts->lessonSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->lessonVersion(), PromptShape::Full, hash('sha256', $text));

        $schema = PlanSchemas::lesson($request->dialogueCount, $request->vocabularyCount);

        return self::reply($model->complete($prompt, $this->prompts->lessonUser($request), $schema), $prompt->version);
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        $model = $this->model($this->repairModel, $this->lessonTimeout, journalPurpose: self::JOURNAL_REPAIR);
        $text = $this->prompts->repairSystem($request->kind);
        $prompt = new RenderedPrompt($text, $this->prompts->repairVersion(), PromptShape::Full, hash('sha256', $text));
        $schema = PlanSchemas::lessonCard($request->kind, $request->dialogueCount, $request->vocabularyCount);

        return self::reply($model->complete($prompt, $this->prompts->repairUser($request), $schema), $prompt->version);
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        $model = $this->model($this->judgeModel, $this->lessonTimeout, journalPurpose: self::JOURNAL_JUDGE);
        $text = $this->prompts->judgeSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->judgeVersion(), PromptShape::Full, hash('sha256', $text));

        return self::reply($model->complete($prompt, $this->prompts->judgeUser($request), PlanSchemas::seamJudge($request->ids())), $prompt->version);
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        $model = $this->model($this->judgeModel, max(1, $this->slotJudgeTimeout), self::SLOT_JUDGE_ATTEMPTS, self::JOURNAL_JUDGE);
        $text = $this->prompts->slotJudgeSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->slotJudgeVersion(), PromptShape::Full, hash('sha256', $text));

        $startedAt = hrtime(true);
        try {
            $reply = self::reply($model->complete($prompt, $this->prompts->slotJudgeUser($request), PlanSchemas::slotJudge()), $prompt->version);
        } catch (Throwable $e) {
            Log::warning('plan.slot_judge', [
                'prompt_version' => $prompt->version,
                'model' => $this->judgeModel,
                'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            throw $e;
        }

        Log::info('plan.slot_judge', [
            'prompt_version' => $reply->promptVersion,
            'model' => $reply->model,
            'tokens_in' => $reply->tokensIn,
            'tokens_out' => $reply->tokensOut,
            'cost_usd' => $reply->costUsd,
            'latency_ms' => $reply->latencyMs,
        ]);

        return $reply;
    }

    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        $model = $this->model($this->conversationModel, max(1, $this->conversationTimeout), self::CONVERSATION_ATTEMPTS, self::JOURNAL_CONVERSATION);
        $text = $this->prompts->conversationSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->conversationVersion(), PromptShape::Full, hash('sha256', $text));

        $startedAt = hrtime(true);
        try {
            $reply = self::reply(
                $model->complete($prompt, $this->prompts->conversationUser($request), PlanSchemas::conversationAgent($request->phraseIds())),
                $prompt->version,
            );
        } catch (Throwable $e) {
            Log::warning('plan.conversation', [
                'prompt_version' => $prompt->version,
                'model' => $this->conversationModel,
                'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            throw $e;
        }

        Log::info('plan.conversation', [
            'prompt_version' => $reply->promptVersion,
            'model' => $reply->model,
            'tokens_in' => $reply->tokensIn,
            'tokens_out' => $reply->tokensOut,
            'cost_usd' => $reply->costUsd,
            'latency_ms' => $reply->latencyMs,
        ]);

        return $reply;
    }

    public function planPromptVersion(): string
    {
        return $this->prompts->planVersion();
    }

    public function repairPromptVersion(): string
    {
        return $this->prompts->repairVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->prompts->lessonVersion();
    }

    public function judgePromptVersion(): string
    {
        return $this->prompts->judgeVersion();
    }

    public function slotJudgePromptVersion(): string
    {
        return $this->prompts->slotJudgeVersion();
    }

    public function conversationPromptVersion(): string
    {
        return $this->prompts->conversationVersion();
    }

    private function model(string $name, int $timeout, ?int $attempts = null, ?string $journalPurpose = null): ContentModelPort
    {
        $port = $this->catalog->get($this->provider, $name, self::PURPOSE, $timeout, $attempts, $journalPurpose);
        if ($port === null) {
            throw PlanModelUnavailable::because("провайдер «{$this->provider->value}» не настроен (нет ключа)");
        }

        return $port;
    }

    private static function reply(ModelAnswer $answer, string $promptVersion): ModelReply
    {
        return new ModelReply(
            payload: $answer->payload,
            promptVersion: $promptVersion,
            model: $answer->model,
            tokensIn: $answer->tokensIn,
            tokensOut: $answer->tokensOut,
            costUsd: $answer->costUsd ?? '0.000000',
            latencyMs: $answer->latencyMs,
            raw: $answer->raw,
            cachedTokensIn: $answer->cachedTokensIn,
        );
    }
}
