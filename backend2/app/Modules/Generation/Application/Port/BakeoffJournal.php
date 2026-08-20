<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\BakeoffCallResult;

/**
 * Where a bake-off writes: the sandbox, and nowhere else.
 *
 * The port is narrow on purpose — open a run, append call results, and nothing more. There is no
 * method here that could write a term, a translation or an example, so "the bake-off must not touch
 * live content" is a property of the type rather than a rule someone has to remember.
 */
interface BakeoffJournal
{
    /**
     * Start a run and return its id.
     *
     * @param  array<string, mixed>  $notes  provider availability, sample composition — whatever the
     *                                       report has to be able to state about how the run was set up
     */
    public function openRun(string $label, string $promptVersion, string $sourceLang, string $targetLang, array $notes): string;

    /** Append one call and its candidates. Returns the call id. */
    public function recordCall(string $runId, BakeoffCallResult $result): string;

    /**
     * Read a finished run back out of the sandbox.
     *
     * This is why the candidates are persisted at all. A comparison document gets re-read, argued
     * with and improved, and re-rendering it must not mean paying for the model calls a second time
     * — nor accepting whatever the report looked like the night it was generated. The provider
     * answers are the expensive, irreplaceable part; the rendering is not.
     *
     * @return array{results: list<BakeoffCallResult>, run: array<string, mixed>}|null  null = no such run
     */
    public function readRun(string $runId): ?array;

    /**
     * The CORES a finished run produced — term, key, example — ready to be handed to a later stage.
     *
     * This is what makes a two-stage config measurable end to end: the machinery stage must run over
     * the very cores the collection stage produced, not over a sample of live content, or the two
     * halves are being measured on different material and their costs cannot be added up.
     *
     * @param  string|null  $provider  narrow to one provider's cores; null takes whatever the run has
     * @return list<array{id: string, text: string, translation: string, example: string, example_translation: string}>
     */
    public function readCores(string $runId, ?string $provider = null, ?string $track = null): array;
}
