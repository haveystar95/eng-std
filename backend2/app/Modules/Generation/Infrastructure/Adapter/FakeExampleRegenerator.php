<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ExampleRegenBrief;
use App\Modules\Generation\Application\Dto\ExampleRegenResult;
use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;

/** Deterministic regenerator — no network. Always differs from the avoided example. */
final class FakeExampleRegenerator implements ExampleRegeneratorPort
{
    public function regenerate(ExampleRegenBrief $brief): ExampleRegenResult
    {
        return new ExampleRegenResult(
            example: 'A fresh example with ' . $brief->text . ' in a new situation.',
            exampleTranslation: 'Свежий пример со словом ' . $brief->text . ' в новой ситуации.',
            model: 'fake',
            tokensIn: 30,
            tokensOut: 45,
            promptVersion: 'ex-regen.fake',
        );
    }
}
