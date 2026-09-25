<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE SCENE A DAY IS MADE OF (наряд BACK-TAILS-2 §4, `window.sources[]`): «Из каких сцен» of the rehearsal, «Из каких
 * дней» of a review (кадры 37-1, 37-2), the day's own scene of a scene day — the scene, its name as the plan gives it and
 * the day of the route it stands on — null for a scene with no day of its own, which only a stand built by hand has —,
 * and the grammatical gender of its partner's role (`male` / `female`, наряд FIX-4c §3: «Медсестра начнёт первой»), the
 * gender the lesson gave the role, the default cast's while it has none. The same object names the scene of an item of a
 * programme tab (наряд FIX-3 §9).
 */
final readonly class WindowSourceView
{
    /** An item of a programme tab that is the day's own (наряд FIX-3 §9). */
    public const OWN = 'own';

    /** An item that came back from an earlier day. */
    public const RETURNED = 'returned';

    public function __construct(
        public string $sceneId,
        public string $titleNative,
        public ?int $dayNumber,
        public string $partnerGender = 'female',
    ) {}
}
