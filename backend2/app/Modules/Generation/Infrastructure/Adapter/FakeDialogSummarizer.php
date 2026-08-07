<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\DialogSummaryBrief;
use App\Modules\Generation\Application\Dto\DialogSummaryResult;
use App\Modules\Generation\Application\Port\DialogSummarizerPort;

/** Deterministic summariser — no network. Echoes the topic and how many lines were spoken. */
final class FakeDialogSummarizer implements DialogSummarizerPort
{
    public function summarize(DialogSummaryBrief $brief): DialogSummaryResult
    {
        $lineCount = count($brief->lines);

        return new DialogSummaryResult(
            summary: "Practice recap for \"{$brief->topic}\": {$lineCount} lines exchanged. Good effort — keep going.",
            tokensIn: 40,
            tokensOut: 20,
            model: 'fake',
        );
    }
}
