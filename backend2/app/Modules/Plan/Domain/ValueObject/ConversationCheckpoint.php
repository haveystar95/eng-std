<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * ONE SCENE OF THE TALK (наряд CONV-1): what it is about, who the role is IN IT, and the lines the
 * learner is preparing to say there.
 *
 * A day's talk has one of these; the rehearsal walks them all in the plan's order, and the role
 * changes with them — the person at the reception desk is not the doctor. That is why the role and
 * the voice's gender live on the checkpoint and not on the talk.
 */
final readonly class ConversationCheckpoint
{
    /**
     * @param  list<array{target: string, native: string, phrase_ref: string|null}>  $keyLines  the learner's own
     *   lines of the scene, in order, each with the frame it stands on — that ref is how the hint knows which
     *   line has already been said and which one to offer next
     */
    public function __construct(
        public string $sceneId,
        public string $titleNative,
        public string $titleTarget,
        public string $aboutNative,
        public string $roleTarget,
        public string $roleNative,
        public VoiceGender $partnerGender,
        public array $keyLines,
    ) {}
}
