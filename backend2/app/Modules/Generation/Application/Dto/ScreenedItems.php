<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\RejectedItem;

/**
 * What the language barrier let through, what it refused, and every repair call it paid for.
 *
 * `repairs` holds EVERY call, including the ones whose answer was still in the wrong language — a
 * failed repair costs exactly as much as a successful one, and a spend log that only counted the
 * successes would under-report the price of a bad run.
 */
final readonly class ScreenedItems
{
    /**
     * @param  list<GeneratedItem>  $items
     * @param  list<RejectedItem>  $rejections
     * @param  list<RepairedTranslation>  $repairs
     */
    public function __construct(
        public array $items,
        public array $rejections,
        public array $repairs,
    ) {}
}
