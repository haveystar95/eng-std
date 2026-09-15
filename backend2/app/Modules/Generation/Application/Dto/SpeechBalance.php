<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What the voice vendor's account has left for the month, as the vendor counts it (TTS-2): characters (credits)
 * used and the plan's limit, and when the count starts over.
 */
final readonly class SpeechBalance
{
    public function __construct(
        public int $used,
        public int $limit,
        public ?int $resetsAtUnix,
    ) {}
}
