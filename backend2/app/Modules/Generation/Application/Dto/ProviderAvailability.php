<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\ProviderId;

/** A provider, the model it is configured with, and whether it can be called — with the reason. */
final readonly class ProviderAvailability
{
    public function __construct(
        public ProviderId $provider,
        public string $model,
        public bool $available,
        /** Why not, in one phrase, for the report. Empty when it IS available. */
        public string $reason = '',
    ) {}

    public static function ready(ProviderId $provider, string $model): self
    {
        return new self($provider, $model, true);
    }

    public static function missingKey(ProviderId $provider, string $model, string $envVar): self
    {
        return new self($provider, $model, false, "нет ключа ({$envVar} не задан)");
    }
}
