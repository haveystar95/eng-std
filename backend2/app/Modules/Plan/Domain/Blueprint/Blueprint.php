<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Blueprint;

/**
 * The plan builder's answer: either `ok` with titles and scenes, or `unclear` with a reason. An
 * `unclear` answer is a legitimate outcome, not a failure — it is stored on the plan and shown.
 */
final readonly class Blueprint
{
    public const STATUS_OK = 'ok';

    public const STATUS_UNCLEAR = 'unclear';

    /** @param list<SceneBrief> $scenes */
    public function __construct(
        public string $status,
        public ?string $unclearReason,
        public ?PlanTitles $titles,
        public array $scenes,
    ) {}

    public function isUnclear(): bool
    {
        return $this->status === self::STATUS_UNCLEAR;
    }

    /** @param list<SceneBrief> $scenes */
    public function withScenes(array $scenes): self
    {
        return new self($this->status, $this->unclearReason, $this->titles, $scenes);
    }
}
