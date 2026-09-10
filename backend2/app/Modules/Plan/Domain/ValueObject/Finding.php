<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** One firing of one check on one model answer — what the counters and `checks_json` are made of. */
final readonly class Finding
{
    public function __construct(
        public string $check,
        public CheckMode $mode,
        public CheckAction $action,
        public string $detail,
    ) {}

    /** @return array{check: string, mode: string, action: string, detail: string} */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'mode' => $this->mode->value,
            'action' => $this->action->value,
            'detail' => $this->detail,
        ];
    }
}
