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
use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Service\ModelText;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The plan's model calls over Generation's vendor seam: the prompt file is the system side, the inputs
 * are the user side, the schema is enforced by the vendor, and the request log labels the spend
 * `plan` — one budget — while the journal of model calls names each call by its PURPOSE (наряд GEN-4):
 * `plan`, `plan_line_repair`, `skeleton`, `dialogue`, `repair`, `seam_judge`, `slot_judge`, `conversation`.
 *
 * Every purpose has its own model and reasoning effort (`plan.model.purposes`, {@see PlanModelChoice}): the plan on the strong
 * model, the day's two stages, their repairs and the plan's line repairs on a cheaper one, the two judges on a `mini`
 * model — the slot judge the one call a learner WAITS on besides the talk (наряд SESSION-1a, разд. 4), so it gets one
 * attempt and its own short timeout, and says in the log what it cost. The conversation agent keeps its own model and
 * timeout (`plan.conversation`): synchronous, one attempt, a `mini` model because a turn has six seconds to come back.
 *
 * The rules go first and the same for every call (the prompt file as the system message, and a schema that names no id of
 * the day), the day's inputs after them (наряд GEN-3) — so the vendor serves the rules from its prompt cache; what it
 * served is on the reply ({@see ModelReply::$cachedTokensIn}) and in the journal of model calls.
 */
final readonly class ContentModelPlanBuilder implements PlanModelPort
{
    /** What the plan's spend is called in the request log — one budget, one label. */
    public const PURPOSE = 'plan';

    /** The purposes — each call's name in the journal of model calls and its key in `plan.model.purposes`. */
    public const PLAN = 'plan';

    public const PLAN_LINE_REPAIR = 'plan_line_repair';

    public const SKELETON = 'skeleton';

    public const DIALOGUE = 'dialogue';

    public const REPAIR = 'repair';

    public const SEAM_JUDGE = 'seam_judge';

    public const SLOT_JUDGE = 'slot_judge';

    /** The talk with the agent (наряд CONV-1) — its own name in the journal, its own model, its own timeout. */
    public const CONVERSATION = 'conversation';

    /** Every purpose `plan.model.purposes` names. */
    public const PURPOSES = [self::PLAN, self::PLAN_LINE_REPAIR, self::SKELETON, self::DIALOGUE, self::REPAIR, self::SEAM_JUDGE, self::SLOT_JUDGE];

    /** The slot judge's attempts: one (D-28) — past its timeout the code rules, a retry would only lengthen the wait. */
    public const SLOT_JUDGE_ATTEMPTS = 1;

    /** Seconds of that one attempt when the caller names none — `plan.slot_judge.timeout`'s own default. */
    public const SLOT_JUDGE_TIMEOUT = 8;

    /** The agent's attempts: one. The learner is sitting through this wait; a retry doubles it. */
    public const CONVERSATION_ATTEMPTS = 1;

    /** Seconds of that one attempt when the caller names none — `plan.conversation.timeout`'s own default. */
    public const CONVERSATION_TIMEOUT = 20;

    /** Seconds a line repair waits: a line in, a line out. */
    public const PLAN_LINE_TIMEOUT = 30;

    /**
     * @param  array<string, PlanModelChoice>  $choices  purpose → model and reasoning effort; a purpose not named takes `gpt-5.4`
     */
    public function __construct(
        private ContentModelCatalog $catalog,
        private PlanPromptFiles $prompts,
        private ProviderId $provider,
        private array $choices,
        private int $planTimeout,
        private int $lessonTimeout,
        /** Seconds of the slot judge's one attempt — `plan.slot_judge.timeout`, threaded in by the provider. */
        private int $slotJudgeTimeout = self::SLOT_JUDGE_TIMEOUT,
        /** The agent of the talk — a `mini` class model: the learner waits for it (наряд CONV-1, п. 4). */
        private string $conversationModel = 'gpt-5.4-mini',
        private int $conversationTimeout = self::CONVERSATION_TIMEOUT,
    ) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        return $this->ask(self::PLAN, $this->planTimeout, $this->prompts->planSystem(), $this->prompts->planVersion(), $this->prompts->planUser($request), PlanSchemas::plan());
    }

    public function repairPlanLine(PlanLineRepairRequest $request): ModelReply
    {
        return $this->ask(self::PLAN_LINE_REPAIR, min($this->planTimeout, self::PLAN_LINE_TIMEOUT), $this->prompts->planLineSystem(), $this->prompts->planLineVersion(), $this->prompts->planLineUser($request), PlanSchemas::planLine());
    }

    public function buildSkeleton(LessonRequest $request): ModelReply
    {
        return $this->ask(self::SKELETON, $this->lessonTimeout, $this->prompts->skeletonSystem(), $this->prompts->skeletonVersion(), $this->prompts->skeletonUser($request), PlanSchemas::skeleton());
    }

    public function buildDialogue(DialogueRequest $request): ModelReply
    {
        return $this->ask(self::DIALOGUE, $this->lessonTimeout, $this->prompts->dialogueSystem(), $this->prompts->dialogueVersion(), $this->prompts->dialogueUser($request), PlanSchemas::dialogue());
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->ask(self::REPAIR, $this->lessonTimeout, $this->prompts->repairSystem($request->kind), $this->prompts->repairVersion(), $this->prompts->repairUser($request), PlanSchemas::lessonCard($request->kind));
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        return $this->ask(self::SEAM_JUDGE, $this->lessonTimeout, $this->prompts->judgeSystem(), $this->prompts->judgeVersion(), $this->prompts->judgeUser($request), PlanSchemas::seamJudge($request->ids(), $request->replyIds()));
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        $model = $this->model(self::SLOT_JUDGE, max(1, $this->slotJudgeTimeout), self::SLOT_JUDGE_ATTEMPTS);
        $text = $this->prompts->slotJudgeSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->slotJudgeVersion(), PromptShape::Full, hash('sha256', $text));

        $startedAt = hrtime(true);
        try {
            $reply = self::reply($model->complete($prompt, $this->prompts->slotJudgeUser($request), PlanSchemas::slotJudge()), $prompt->version);
        } catch (Throwable $e) {
            Log::warning('plan.slot_judge', [
                'prompt_version' => $prompt->version,
                'model' => $this->choice(self::SLOT_JUDGE)->model,
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
        $model = $this->catalogModel($this->conversationModel, null, max(1, $this->conversationTimeout), self::CONVERSATION_ATTEMPTS, self::CONVERSATION);
        $text = $this->prompts->conversationSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->conversationVersion(), PromptShape::Full, hash('sha256', $text));

        $startedAt = hrtime(true);
        try {
            $reply = self::reply(
                $model->complete($prompt, $this->prompts->conversationUser($request), PlanSchemas::conversationAgent($request->targetIds())),
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

    public function skeletonPromptVersion(): string
    {
        return $this->prompts->skeletonVersion();
    }

    public function dialoguePromptVersion(): string
    {
        return $this->prompts->dialogueVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->prompts->skeletonVersion().'+'.$this->prompts->dialogueVersion();
    }

    public function repairPromptVersion(): string
    {
        return $this->prompts->repairVersion();
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

    /**
     * One call of a purpose: its model and reasoning effort, the prompt as the system side, the inputs as the user side.
     *
     * @param  array<string, mixed>  $schema
     */
    private function ask(string $purpose, int $timeout, string $system, string $version, string $user, array $schema): ModelReply
    {
        $prompt = new RenderedPrompt($system, $version, PromptShape::Full, hash('sha256', $system));

        return self::reply($this->model($purpose, $timeout)->complete($prompt, $user, $schema), $prompt->version);
    }

    /** The model a purpose is configured with — `gpt-5.4` and the model's own reasoning when the config names none. */
    private function choice(string $purpose): PlanModelChoice
    {
        return $this->choices[$purpose] ?? new PlanModelChoice('gpt-5.4');
    }

    private function model(string $purpose, int $timeout, ?int $attempts = null): ContentModelPort
    {
        $choice = $this->choice($purpose);

        return $this->catalogModel($choice->model, $choice->reasoningEffort, $timeout, $attempts, $purpose);
    }

    private function catalogModel(string $name, ?string $reasoningEffort, int $timeout, ?int $attempts, string $journalPurpose): ContentModelPort
    {
        $port = $this->catalog->get($this->provider, $name, self::PURPOSE, $timeout, $attempts, $journalPurpose, $reasoningEffort);
        if ($port === null) {
            throw PlanModelUnavailable::because("провайдер «{$this->provider->value}» не настроен (нет ключа)");
        }

        return $port;
    }

    /**
     * Every answer of the plan's model comes in here — and comes in without the characters that print nothing (наряд
     * LANG-1b §6, {@see ModelText}): a control character in a word of a day's title, a soft hyphen, a zero-width space.
     */
    private static function reply(ModelAnswer $answer, string $promptVersion): ModelReply
    {
        return new ModelReply(
            payload: ModelText::visible($answer->payload),
            promptVersion: $promptVersion,
            model: $answer->model,
            tokensIn: $answer->tokensIn,
            tokensOut: $answer->tokensOut,
            costUsd: $answer->costUsd ?? '0.000000',
            latencyMs: $answer->latencyMs,
            raw: $answer->raw,
            cachedTokensIn: $answer->cachedTokensIn,
            callId: $answer->callId,
        );
    }
}
