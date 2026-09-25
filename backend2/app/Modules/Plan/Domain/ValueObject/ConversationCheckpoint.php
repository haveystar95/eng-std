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
 * the voice live on the checkpoint and not on the talk: its gender, and the voice fixed for the scene (наряд FIX-4c §1 —
 * two women of two scenes are two voices).
 */
final readonly class ConversationCheckpoint
{
    /**
     * @param  list<array{target: string, native: string, phrase_ref: string|null, kind?: string, partner?: string}>  $keyLines  the
     *   learner's own lines of the scene, in order, each with the frame it stands on — that ref is how the hint knows
     *   which line has already been said and which one to offer next — and the exchange it stands in: `kind` (`ask` —
     *   the learner asks, the role answers; `answer` — the role speaks, the learner answers) and the role's own line
     *   there (`partner`), so the role is told both sides of the visit (наряд CONV-2, п. 1)
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
        public ?string $partnerVoice = null,
    ) {}
}
