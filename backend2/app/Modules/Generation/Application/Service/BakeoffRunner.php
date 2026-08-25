<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\BakeoffCallResult;
use App\Modules\Generation\Application\Dto\BakeoffTask;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PromptSource;
use App\Modules\Generation\Domain\Service\ContentChecks;
use App\Modules\Shared\Domain\Service\LanguageName;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Throwable;

/**
 * One task, one provider, one measured answer.
 *
 * Everything that must be IDENTICAL across providers is decided here and not by the caller: the
 * prompt version, the rendered rules, the schema, and the checks. A loop that built the prompt per
 * provider is how a comparison quietly becomes a comparison of prompts.
 *
 * It reads no live content and writes none. The terms a task carries are handed in by the caller,
 * already read; the answers go to the sandbox journal. There is no path from here to `terms`.
 */
final readonly class BakeoffRunner
{
    public function __construct(
        private PromptSource $prompts,
        private ContentContract $contract,
        private ContentChecks $checks,
    ) {}

    public function run(
        ContentModelPort $provider,
        BakeoffTask $task,
        string $promptVersion,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        string $levels = 'A2, B1',
    ): BakeoffCallResult {
        $shape = $task->track->shape();
        $prompt = $this->prompts->render($promptVersion, $shape, [
            'source_lang' => LanguageName::of($sourceLang->value),
            'target_lang' => LanguageName::of($targetLang->value),
            'levels' => $levels,
            'size' => (string) ($task->expectedSize ?? count($task->terms)),
        ]);

        try {
            // Same version the prompt was rendered from, one line up: a bake-off that measured a
            // version against a schema belonging to another one would be measuring the mismatch.
            $answer = $provider->complete($prompt, $task->userMessage, $this->contract->schema($shape, $promptVersion));
        } catch (Throwable $e) {
            // A dead call is data, not the end of the run — see BakeoffCallResult.
            return BakeoffCallResult::failed(
                $task->track, $provider->provider(), $provider->model(), $task->key, $prompt->sha256,
                mb_substr($e->getMessage(), 0, 1000),
            );
        }

        $items = $this->contract->items($answer->payload, $task->terms);
        $batch = $this->checks->judge(
            $items,
            $shape,
            $sourceLang->value,
            $targetLang->value,
            $task->expectedSize,
        );

        return BakeoffCallResult::answered(
            $task->track, $provider->provider(), $answer->model, $task->key, $prompt->sha256, $batch, $answer,
        );
    }
}
