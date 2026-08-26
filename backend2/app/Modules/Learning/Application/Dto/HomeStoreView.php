<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** «…или выбрать из 17 готовых» — the store as the home screen needs it: a number and a taste. */
final readonly class HomeStoreView
{
    /** @param list<string> $topics */
    public function __construct(
        public int $count,
        public array $topics,
    ) {}
}
