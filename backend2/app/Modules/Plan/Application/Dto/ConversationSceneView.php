<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE SCENE OF THE TALK as the client draws it: the strip above the ribbon («Приём у врача · врач»,
 * кадр 30-2b) and, on the rehearsal, the list «Из каких сцен» (кадр 37-1) with where the talk is.
 */
final readonly class ConversationSceneView
{
    public const CURRENT = 'current';

    public const DONE = 'done';

    public const LOCKED = 'locked';

    public function __construct(
        public string $sceneId,
        public string $titleNative,
        public string $titleTarget,
        public string $roleNative,
        public string $roleTarget,
        public int $lines,
        public string $state,
    ) {}
}
