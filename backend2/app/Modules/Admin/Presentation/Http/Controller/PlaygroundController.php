<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\StartPlaygroundRun;
use App\Modules\Admin\Application\Command\StartPlaygroundRunHandler;
use App\Modules\Admin\Application\Query\DryRunDistractorValidation;
use App\Modules\Admin\Application\Query\DryRunDistractorValidationHandler;
use App\Modules\Admin\Application\Query\GetPlaygroundRun;
use App\Modules\Admin\Application\Query\GetPlaygroundRunHandler;
use App\Modules\Admin\Application\Query\ListPlaygroundProviders;
use App\Modules\Admin\Application\Query\ListPlaygroundProvidersHandler;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Request\PlaygroundGenerateRequest;
use App\Modules\Admin\Presentation\Http\Request\PlaygroundValidateRequest;
use Illuminate\Http\JsonResponse;

/**
 * «Песочница»: try a prompt on a model, then run the result past the real distractor validator.
 *
 * TWO invariants, and both are the reason this surface can exist at all:
 *
 *  1. **Nothing of the catalogue is written.** Not a distractor, not a suppression, not a version
 *     mark. The generate side starts a run — a queued call the screen polls (наряд GEN-3), whose answer waits in the
 *     cache and whose spend is in the journal of model calls; the validate side calls a pure Domain service. Neither has
 *     a repository of content in reach, and `AdminPlaygroundTest` counts rows across both to keep it that way.
 *  2. **The validator is the real one.** The verdicts come from
 *     {@see \App\Modules\Generation\Domain\Service\EnrichmentValidator} — the same object the станок
 *     runs — so a row that survives here survives there. A sandbox that agreed with production only
 *     approximately would be worse than none: it would be believed.
 *
 * A vendor failure is an ANSWER, not a 500: keys expire, orgs run out of credits, model names go
 * stale, and every one of those is a result the person running the experiment needs to read.
 */
final class PlaygroundController
{
    public function __construct(
        private readonly ListPlaygroundProvidersHandler $providers,
        private readonly StartPlaygroundRunHandler $start,
        private readonly GetPlaygroundRunHandler $run,
        private readonly DryRunDistractorValidationHandler $validate,
    ) {}

    /** The picker: every provider, with the models it offers and why it may be unusable. */
    public function providers(): JsonResponse
    {
        return response()->json([
            'data' => array_map(AdminJson::playgroundProvider(...), ($this->providers)(new ListPlaygroundProviders())),
        ]);
    }

    /** Starts a run: 202 with its id — the call is made by a queued job, and the screen polls {@see runAction()}. */
    public function generateAction(PlaygroundGenerateRequest $request): JsonResponse
    {
        $temperature = $request->has('temperature') && $request->input('temperature') !== null
            ? (float) $request->input('temperature')
            : null;

        $id = ($this->start)(new StartPlaygroundRun(
            provider: (string) $request->input('provider'),
            model: (string) $request->input('model'),
            // Verbatim. Trimming or templating the prompt here would make the sandbox measure this
            // code instead of the model.
            prompt: (string) $request->input('prompt'),
            temperature: $temperature,
        ));

        return response()->json(['id' => $id, 'status' => 'queued'], 202);
    }

    /** A run as it stands: `queued`, `running`, or `done` with its result; 404 for a run nobody started (or a day gone). */
    public function runAction(string $id): JsonResponse
    {
        $run = ($this->run)(new GetPlaygroundRun($id));
        if ($run === null) {
            return response()->json(['message' => 'Прогон не найден.'], 404);
        }

        return response()->json(AdminJson::playgroundRun($run));
    }

    public function validateAction(PlaygroundValidateRequest $request): JsonResponse
    {
        /** @var list<array{sentence: string, error_span: string, correction: string, error_type?: string|null}> $items */
        $items = array_values($request->array('items'));

        return response()->json(AdminJson::playgroundValidation(($this->validate)(new DryRunDistractorValidation(
            items: $items,
            termId: $request->input('term_id') !== null ? (string) $request->input('term_id') : null,
            manualTerm: $request->input('manual.term_text') !== null ? (string) $request->input('manual.term_text') : null,
            manualExample: $request->input('manual.example_text') !== null ? (string) $request->input('manual.example_text') : null,
        ))));
    }
}
