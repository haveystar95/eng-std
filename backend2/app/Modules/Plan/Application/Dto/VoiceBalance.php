<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use DateTimeImmutable;

/**
 * What the voice vendor's account has left this month (TTS-2): credits used and the limit, and where the numbers came
 * from — the vendor's own count (`vendor`), or the credits debited for the lines in `plan_line_audios` since the start
 * of the month against the configured plan size (`counter`), when the vendor would not say.
 */
final readonly class VoiceBalance
{
    public const VENDOR = 'vendor';
    public const COUNTER = 'counter';

    public function __construct(
        public int $used,
        public int $limit,
        public string $source,
        public ?DateTimeImmutable $resetsAt = null,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->limit - $this->used);
    }

    /** The share of the limit still left, 0…1; an account without a limit has nothing left. */
    public function remainingShare(): float
    {
        return $this->limit <= 0 ? 0.0 : $this->remaining() / $this->limit;
    }
}
