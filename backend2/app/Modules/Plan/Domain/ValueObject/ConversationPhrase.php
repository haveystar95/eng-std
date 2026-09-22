<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\IntentClause;

/**
 * ONE TARGET OF A TALK — A CONSTRUCTION, NOT A SENTENCE (наряд FIX-3 §6): the frame with its window («I have ___ of
 * experience.»), the value the lesson says it with (`example`, grey in the window on the screen: «about a year»), which
 * scene it belongs to, its ref (`p2`), and whether the learner opens it (`ask`) or answers with it (`answer`).
 *
 * The learner is not asked to say the lesson's sentence — «I have about a year of experience» is somebody else's year.
 * They are asked to say the CONSTRUCTION with a value of their own, and the talk ticks it when the frame's words and
 * something in its window are heard ({@see \App\Modules\Plan\Domain\Service\PhraseUse}). A frame with no window is said
 * as it is: its example is null.
 *
 * The id is scene-qualified on purpose: `p3` means nothing on its own in a rehearsal that walks three scenes.
 */
final readonly class ConversationPhrase
{
    public function __construct(
        public string $sceneId,
        public string $ref,
        public string $frameTarget,
        public string $frameNative,
        public ?string $exampleTarget,
        public ?string $exampleNative,
        public ExchangeKind $kind = ExchangeKind::Answer,
    ) {}

    public function id(): string
    {
        return $this->sceneId.':'.$this->ref;
    }

    /** Does the construction have a window for a value of the learner's own? */
    public function hasWindow(): bool
    {
        return FrameText::hasSlot($this->frameTarget);
    }

    /**
     * THE HINT FOR THIS TARGET — the construction in the learner's language as the clause of «Скажи, что …» (наряд FIX-3
     * §6; {@see IntentClause}): «я работаю над …». The window is an ellipsis — what goes in it is the learner's to say.
     */
    public function hintNative(): string
    {
        return IntentClause::of((string) preg_replace(FrameText::SLOT_PATTERN, '…', $this->frameNative));
    }
}
