<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Blueprint;

/** The plan-level strings the builder writes: names, the event's noun and its inflected forms. */
final readonly class PlanTitles
{
    public function __construct(
        public string $titleNative,
        public string $titleTarget,
        public string $eventNative,
        public string $untilPhraseNative,
        public string $overdueNative,
        public string $coverImagePrompt,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
    ) {}
}
