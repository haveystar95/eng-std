<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;

/**
 * Deterministic generator — no network. Bound when GENERATION_DRIVER=fake so the console
 * and feature tests can exercise the full pipeline without an API key or spend.
 */
final class FakeCollectionGenerator implements CollectionGeneratorPort
{
    public function generate(GenerationBrief $brief): GeneratedCollectionDraft
    {
        $cefr = $brief->levels[0] ?? 'B1';
        $count = max(8, $brief->size);

        // A top-up passes the already-accepted texts; offset the numbering past them so this batch
        // is fresh and non-overlapping (the deterministic texts are index-based), exercising the
        // real top-up fill path instead of returning the same items to be deduped away.
        $offset = count($brief->excludeTexts);

        $items = [];
        for ($n = 1; $n <= $count; $n++) {
            $i = $offset + $n;
            $isPhrase = $i % 2 === 0;
            $items[] = new GeneratedItem(
                text: $isPhrase ? "sample phrase {$i}" : "sampleword{$i}",
                type: $isPhrase ? 'phrase' : 'word',
                translation: "образец {$i}",
                example: "This is sample sentence number {$i}.",
                cefr: $cefr,
                transcription: "ˈsæmpəl {$i}",
                exampleTranslation: "Это образец предложения номер {$i}.",
            );
        }

        return new GeneratedCollectionDraft(
            title: 'Sample: ' . $brief->prompt,
            description: 'Deterministic fake collection for ' . $brief->prompt,
            items: $items,
            model: 'fake',
            tokensIn: 100,
            tokensOut: 200,
        );
    }
}
