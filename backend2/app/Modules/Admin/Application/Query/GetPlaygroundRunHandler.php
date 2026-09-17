<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\PlaygroundResult;
use App\Modules\Admin\Application\Dto\PlaygroundRunView;
use App\Modules\Generation\Application\Service\PlaygroundRuns;

/** The run re-shaped for the panel: its state, and once it is done the call as the panel renders it. */
final readonly class GetPlaygroundRunHandler
{
    public function __construct(private PlaygroundRuns $runs) {}

    public function __invoke(GetPlaygroundRun $query): ?PlaygroundRunView
    {
        $run = $this->runs->find($query->runId);
        if ($run === null) {
            return null;
        }
        $answer = $run->answer;

        return new PlaygroundRunView(
            id: $run->id,
            status: $run->status,
            result: $answer === null ? null : new PlaygroundResult(
                provider: $answer->provider,
                model: $answer->model,
                rawText: $answer->rawText,
                parsedJson: $answer->parsedJson,
                parseError: $answer->parseError,
                tokensIn: $answer->tokensIn,
                tokensOut: $answer->tokensOut,
                costUsd: $answer->costUsd,
                latencyMs: $answer->latencyMs,
                error: $answer->error,
            ),
        );
    }
}
