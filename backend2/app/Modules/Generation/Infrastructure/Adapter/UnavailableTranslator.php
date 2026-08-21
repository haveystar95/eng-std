<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\InstantTranslation;
use App\Modules\Generation\Application\Port\TranslationProvider;

/**
 * The translator a deployment with no key gets.
 *
 * A NULL OBJECT rather than a null binding, so «unconfigured» is a state the code can ask about
 * ({@see isAvailable()}) instead of a hole every caller has to remember to check. The endpoint then
 * answers `feature_disabled` — which is the truth, and is not an error: search and the full lookup
 * are untouched, and the learner simply never sees a grey line.
 */
final class UnavailableTranslator implements TranslationProvider
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function translate(string $text, string $source, string $target): ?InstantTranslation
    {
        return null;
    }
}
