<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE HINT OF THE LEARNER'S NEXT MOVE (наряд FIX-4 §5; кадр 37-7): one target of the scene, offered whole —
 * `native`, its sentence in the learner's language as the clause of «Скажи, что …» («у меня есть боль в плече»); after a
 * move that said it ALMOST, `target` — its exact line («I have some shoulder pain.»), for that one move; and which target
 * it is (`sceneId` + `ref`), so the plate of that target can be pointed at.
 */
final readonly class ConversationHintView
{
    public function __construct(
        public string $native,
        public ?string $target,
        public string $sceneId,
        public string $ref,
    ) {}
}
