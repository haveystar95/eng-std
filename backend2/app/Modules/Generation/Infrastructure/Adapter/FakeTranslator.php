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
    /**
     * The few round trips the tests actually make, both ways, so a reversed answer reads like a
     * real one («случай» → «occasion») instead of like a decorated echo of the query. Everything
     * else falls through to the prefixed forms below.
     *
     * @var array<string, string>
     */
    private const LEXICON = [
        'случай' => 'occasion',
        'как дела' => 'how are you',
        'счёт' => 'invoice',
    ];

    public int $calls = 0;

    /** What each call was asked to do, in order: `['auto→en', …]`. Read by the direction tests. */
    /** @var list<string> */
    public array $directions = [];

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
        $this->directions[] = $source . '→' . $target;
        $clean = trim($text);
        if ($clean === '') {
            return null;
        }

        return new InstantTranslation(
            text: self::LEXICON[$clean] ?? $this->generic($clean, $target),
            provider: DeepLTranslator::NAME,
            characters: mb_strlen($clean),
        );
    }

    /**
     * A word the lexicon does not carry, answered in the language it was ASKED for.
     *
     * Keyed on the target and not on the input's alphabet, which is the whole point of the fake
     * now: the real translator is told both languages and obeys, so a fake that guessed from the
     * script would pass tests the vendor would fail.
     */
    private function generic(string $clean, string $target): string
    {
        return strtolower(trim($target)) === 'en' ? 'translated: ' . $clean : 'перевод: ' . $clean;
    }
}
