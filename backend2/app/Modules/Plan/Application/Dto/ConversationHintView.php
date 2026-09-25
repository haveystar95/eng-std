<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE HINT OF THE LEARNER'S NEXT MOVE (наряд FIX-4 §5; кадр 37-7): one target of the scene, offered whole —
 * `sentence`, its sentence in the learner's language as the lesson has it, capital and closing mark and all («У меня есть
 * боль в плече.», «Мне сказать вам его температуру?» — наряд FIX-4b §2: the client shows it as it is, with no «Скажи, что
 * …» around it, which read crooked for a question); after a move that said it ALMOST, `target` — its exact line («I have
 * some shoulder pain.»), for that one move; and which target it is (`sceneId` + `ref`), so the plate of that target can be
 * pointed at. The clause for «Скажи, что …» the build (20) printed (`native`) is gone (наряд ACC-1 §5): the build (21)
 * reads `sentence`.
 */
final readonly class ConversationHintView
{
    public function __construct(
        public string $sentence,
        public ?string $target,
        public string $sceneId,
        public string $ref,
    ) {}
}
