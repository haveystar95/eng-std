<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawVariant;

/**
 * Deterministic packer — no network. Bound when GENERATION_DRIVER=fake, for tests and for proving
 * the wiring (`enrich:backfill --fake`) without spending money.
 *
 * It returns one GOOD distractor and one that the validator MUST scrap (an article-only rewrite,
 * which our normaliser folds onto the target), so a smoke run exercises both branches and the scrap
 * metric is non-zero — a fake that only produces clean rows would let a broken validator look fine.
 */
final class FakeEnrichmentPacker implements EnrichmentPackerPort
{
    public function pack(EnrichmentBrief $brief): EnrichmentPack
    {
        $sentence = $brief->exampleSentence;

        return new EnrichmentPack(
            distractors: $sentence === null ? [] : [
                new RawDistractor(
                    sentence: 'I can to ' . $brief->text . ' it.',
                    errorType: 'modal_to',
                    errorSpan: 'can to',
                    correction: 'can',
                ),
                // Scrap: "the X" normalises to "X", so this is the accepted answer, not an error.
                new RawDistractor(
                    sentence: 'The ' . $brief->text,
                    errorType: 'article',
                    errorSpan: 'The',
                    correction: '',
                ),
            ],
            variants: [new RawVariant('fake variant of ' . $brief->text, 'то же значение')],
            backTranslation: $brief->text,
            languageNotes: [],
            model: 'fake',
            tokensIn: 50,
            tokensOut: 80,
        );
    }
}
