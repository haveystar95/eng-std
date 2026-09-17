<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A sandbox run as the panel polls it: `queued`, `running`, or `done` with its result (наряд GEN-3). */
final readonly class PlaygroundRunView
{
    public function __construct(
        public string $id,
        public string $status,
        public ?PlaygroundResult $result,
    ) {}
}
