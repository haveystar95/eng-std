<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\RepairedTranslation;
use App\Modules\Generation\Application\Dto\TranslationRepairBrief;
use App\Modules\Generation\Application\Port\TranslationRepairPort;

/**
 * Deterministic repairer — no network. Bound when GENERATION_DRIVER=fake so the console and feature
 * tests exercise the barrier's repair path without an API key or spend.
 *
 * It answers in clean Russian regardless of what it was asked, which is the useful default: the
 * fake generator never produces a tainted item, so in a fake run this is only reached if some other
 * code path went wrong. Tests that need a repairer which FAILS script their own double.
 */
final class FakeTranslationRepairer implements TranslationRepairPort
{
    public function repair(TranslationRepairBrief $brief): RepairedTranslation
    {
        return new RepairedTranslation(
            translation: 'перевод: ' . $brief->text,
            exampleTranslation: $brief->sentence !== null ? 'перевод примера: ' . $brief->sentence : null,
            model: 'fake',
            tokensIn: 20,
            tokensOut: 10,
        );
    }
}
