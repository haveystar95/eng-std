<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * A sandbox provider as the picker shows it: its models, and whether it can be called at all.
 *
 * A missing key is a normal state with a named cause, not an error — the same rule the bake-off
 * catalogue follows. The screen greys the option and repeats `reason`, so «почему Anthropic нельзя
 * выбрать» is answered on the spot instead of in the logs.
 */
final readonly class PlaygroundProvider
{
    /**
     * `provider` is the WIRE name (`openai`, `anthropic`), not the enum: this DTO crosses into the
     * admin module, and a reporting projection may not import another module's Domain.
     *
     * @param  list<string>  $models
     */
    public function __construct(
        public string $provider,
        public string $label,
        public array $models,
        public bool $available,
        public string $reason = '',
    ) {}

    public function defaultModel(): ?string
    {
        return $this->models[0] ?? null;
    }
}
