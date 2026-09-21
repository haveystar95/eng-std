<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE SCENE A DAY IS MADE OF (наряд BACK-TAILS-2 §4, `window.sources[]`): «Из каких сцен» of the rehearsal, «Из каких
 * дней» of a review (кадры 37-1, 37-2), the day's own scene of a scene day — the scene, its name as the plan gives it and
 * the day of the route it stands on — null for a scene with no day of its own, which only a stand built by hand has.
 */
final readonly class WindowSourceView
{
    public function __construct(
        public string $sceneId,
        public string $titleNative,
        public ?int $dayNumber,
    ) {}
}
