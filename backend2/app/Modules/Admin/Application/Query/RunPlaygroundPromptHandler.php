<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\PlaygroundResult;
use App\Modules\Generation\Application\Service\PlaygroundCall;

/**
 * The sandbox call, re-shaped for the panel.
 *
 * A pass-through by design: the provider name stays a STRING all the way down, because Generation's
 * `ProviderId` is that module's Domain and a back-office projection may not import it. Every failure
 * — unknown provider, missing key, unlisted model, a dead vendor — comes back inside the answer as
 * text, which is where the person running the experiment is looking.
 */
final readonly class RunPlaygroundPromptHandler
{
    public function __construct(private PlaygroundCall $call) {}

    public function __invoke(RunPlaygroundPrompt $query): PlaygroundResult
    {
        $answer = $this->call->run($query->provider, $query->model, $query->prompt, $query->temperature);

        return new PlaygroundResult(
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
        );
    }
}
