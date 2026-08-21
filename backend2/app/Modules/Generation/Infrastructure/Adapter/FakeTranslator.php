<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\InstantTranslation;
use App\Modules\Generation\Application\Port\TranslationProvider;

/**
 * Deterministic translator — no network, no quota.
 *
 * It COUNTS its calls, which is the point: the rules worth testing here are all about when the
 * vendor is and is not reached (a cache hit must not, a word already in the catalogue must not, an
 * exhausted month must not), and those are assertions about a call that did not happen.
 */
final class FakeTranslator implements TranslationProvider
{
    public int $calls = 0;

    public function __construct(private readonly bool $available = true) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function name(): string
    {
        return DeepLTranslator::NAME;
    }

    public function translate(string $text, string $source, string $target): ?InstantTranslation
    {
        $this->calls++;
        $clean = trim($text);
        if ($clean === '') {
            return null;
        }

        return new InstantTranslation(
            text: 'перевод: ' . $clean,
            provider: DeepLTranslator::NAME,
            characters: mb_strlen($clean),
        );
    }
}
