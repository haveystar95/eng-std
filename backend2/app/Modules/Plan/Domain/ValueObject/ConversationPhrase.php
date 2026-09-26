<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Service\FrameText;

/**
 * ONE CONSTRUCTION OF A TALK — A FRAME, NOT A SENTENCE (наряд FIX-3 §6): the frame with its window («I have ___ of
 * experience.»), the value the lesson says it with (`example`, grey in the window on the screen: «about a year»), which
 * scene it belongs to, its ref (`p2`), and whether the learner opens it (`ask`) or answers with it (`answer`).
 *
 * The learner is not asked to say the lesson's sentence — «I have about a year of experience» is somebody else's year.
 * They are asked to say the CONSTRUCTION with a value of their own, and the talk ticks it when they say it as a phrase
 * ({@see \App\Modules\Plan\Domain\Service\FrameJudge}, наряд FIX-4 §2). A frame with no window is said as it is: its
 * example is null.
 *
 * `line` is the lesson's own sentence of the construction — the frame said with its value in the target language («I have
 * some shoulder pain.»), and in the learner's the lesson's own line as the model wrote it («У меня есть боль в плече.»,
 * наряд LANG-1b §3 — not the native frame with its value, which may not agree): the hint of the talk offers it whole (наряд
 * FIX-4 §5), and every construction on the wire carries it as `line_native`.
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
        public string $lineTarget = '',
        public string $lineNative = '',
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
}
