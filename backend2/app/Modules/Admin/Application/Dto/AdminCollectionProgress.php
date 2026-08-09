<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Per-collection progress for one user, as computed by Learning (Mastery is the single «усвоено»
 * source). `confirmed`/`familiar` are the breakdown of `mastered`, not a second definition.
 * `new + inProgress + mastered` sum to `total`.
 */
final readonly class AdminCollectionProgress
{
    public function __construct(
        public int $total,
        public int $newCount,
        public int $inProgress,
        public int $mastered,
        public int $confirmed,
        public int $familiar,
        public int $due,
    ) {}
}
