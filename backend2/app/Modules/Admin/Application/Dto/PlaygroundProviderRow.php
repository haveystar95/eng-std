<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One provider in the sandbox picker: its models, and whether it can be called at all. */
final readonly class PlaygroundProviderRow
{
    /** @param  list<string>  $models */
    public function __construct(
        public string $provider,
        public string $label,
        public array $models,
        public bool $available,
        /** Empty when available; otherwise names the env var that would fix it. */
        public string $reason,
    ) {}
}
