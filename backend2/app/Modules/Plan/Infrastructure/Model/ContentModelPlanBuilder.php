<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;

/**
 * The two plan calls over Generation's vendor seam: the prompt file is the system side, the inputs
 * are the user side, the schema is enforced by the vendor, and the request log labels the spend
 * `plan`. Each call is built with the plan's own timeout, not the comparison stack's.
 */
final readonly class ContentModelPlanBuilder implements PlanModelPort
{
    public const PURPOSE = 'plan';

    public function __construct(
        private ContentModelCatalog $catalog,
        private PlanPromptFiles $prompts,
        private ProviderId $provider,
        private string $planModel,
        private string $lessonModel,
        private int $planTimeout,
        private int $lessonTimeout,
    ) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        $model = $this->model($this->planModel, $this->planTimeout);
        $text = $this->prompts->planSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->planVersion(), PromptShape::Full, hash('sha256', $text));

        return self::reply($model->complete($prompt, $this->prompts->planUser($request), PlanSchemas::plan()), $prompt->version);
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        $model = $this->model($this->lessonModel, $this->lessonTimeout);
        $text = $this->prompts->lessonSystem();
        $prompt = new RenderedPrompt($text, $this->prompts->lessonVersion(), PromptShape::Full, hash('sha256', $text));

        return self::reply($model->complete($prompt, $this->prompts->lessonUser($request), PlanSchemas::lesson()), $prompt->version);
    }

    public function planPromptVersion(): string
    {
        return $this->prompts->planVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->prompts->lessonVersion();
    }

    private function model(string $name, int $timeout): ContentModelPort
    {
        $port = $this->catalog->get($this->provider, $name, self::PURPOSE, $timeout);
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
        );
    }
}
