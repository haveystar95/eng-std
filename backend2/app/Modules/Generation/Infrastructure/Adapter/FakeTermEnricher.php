<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\TermEnrichmentBrief;
use App\Modules\Generation\Application\Dto\TermEnrichmentResult;
use App\Modules\Generation\Application\Port\TermEnricherPort;

/** Deterministic enricher — no network. Bound when GENERATION_DRIVER=fake for tests and the console. */
final class FakeTermEnricher implements TermEnricherPort
{
    public function enrich(TermEnrichmentBrief $brief): TermEnrichmentResult
    {
        return new TermEnrichmentResult(
            translation: 'перевод: ' . $brief->text,
            transcription: 'ˈteɪk',
            example: 'This is an example with ' . $brief->text . '.',
            exampleTranslation: 'Это пример со словом ' . $brief->text . '.',
            imageApiPrompt: $brief->text . ' concept',
            model: 'fake',
            tokensIn: 40,
            tokensOut: 60,
            promptVersion: 'enrich-term.fake',
        );
    }
}
