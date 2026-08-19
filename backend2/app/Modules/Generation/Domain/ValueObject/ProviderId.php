<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * The model vendors this app can ask for content. A closed set, because a report groups by it and a
 * free-text vendor name rots the moment two spellings of the same vendor appear in one table.
 *
 * Being listed here is not a claim that the vendor is usable — that depends on a key being present,
 * which is a runtime fact the registry answers (see `ContentModelCatalog::availability()`). A
 * provider without a key is *unavailable*, never an error: a bake-off runs on whoever answers.
 */
enum ProviderId: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Xai = 'xai';

    /** How the provider is written in a report — the vendor's own spelling, not the enum's. */
    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Xai => 'xAI (Grok)',
        };
    }
}
