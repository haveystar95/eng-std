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
}
