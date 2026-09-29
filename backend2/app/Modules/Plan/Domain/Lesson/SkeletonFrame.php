<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * ONE FRAME OF THE SKELETON (`lesson_skeleton.v1.1`, FRAMES; наряд GEN-4): the frame the lesson knows ({@see Phrase} — its
 * pattern in both languages, its reading, its slot with 2–3 fillers) and the `must_say` numbers of the survival set it
 * serves — normally one, two when two items came out as one pattern. The numbers are the skeleton's own: the lesson the
 * learner gets carries the frame without them.
 */
final readonly class SkeletonFrame
{
    /** @param list<int> $mustSay */
    public function __construct(
        public Phrase $phrase,
        public array $mustSay,
    ) {}

    public function id(): string
    {
        return $this->phrase->id;
    }

    public function withPhrase(Phrase $phrase): self
    {
        return new self($phrase, $this->mustSay);
    }

    /**
     * The frame in the skeleton's own shape — the lesson's frame with `must_say` after its kind, the prompt's key order.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $frame = $this->phrase->toArray();

        return [
            'id' => $frame['id'],
            'kind' => $frame['kind'],
            'must_say' => $this->mustSay,
            'frame_target' => $frame['frame_target'],
            'frame_native' => $frame['frame_native'],
            'pronunciation_native' => $frame['pronunciation_native'],
            'slot' => $frame['slot'],
        ];
    }
}
