<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * ONE PHRASE OF THE PLAN AS THE TALK COUNTS IT (наряд CONV-1): which scene it belongs to, its ref
 * (`p3`), the KEY the server listens for, and the two texts the summary prints (кадр 37-12).
 *
 * The key is the frame's own words outside its window ({@see \App\Modules\Plan\Domain\Service\FrameParts::part()}) —
 * the same key «Говорю сам» is judged by. A learner who says the frame with their own value HAS
 * used the phrase of the day; demanding the lesson's filler would count the plan's phrase only when
 * the learner happens to have the lesson's problem.
 *
 * `key` is scene-qualified on purpose: `p3` means nothing on its own in a rehearsal that walks
 * three scenes.
 */
final readonly class ConversationPhrase
{
    public function __construct(
        public string $sceneId,
        public string $ref,
        public string $key,
        public string $textTarget,
        public string $textNative,
        public ?string $audioRef = null,
    ) {}

    public function id(): string
    {
        return $this->sceneId.':'.$this->ref;
    }
}
