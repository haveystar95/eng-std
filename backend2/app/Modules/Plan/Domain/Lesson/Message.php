<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * One chat bubble of an exchange. Speaker A is the partner (text only, no speaking support);
 * speaker B is the learner: the frame the line stands on (`phrase_id`) and the filler it was said
 * with, a reading, a speaking key and simplified variants. A rescue line of the learner carries no
 * frame and no filler.
 */
final readonly class Message
{
    public const SPEAKER_PARTNER = 'A';

    public const SPEAKER_LEARNER = 'B';

    /** @param list<string> $simplifiedVariants */
    public function __construct(
        public string $speaker,
        public string $roleTarget,
        public string $roleNative,
        public string $textTarget,
        public string $textNative,
        public ?string $pronunciationNative = null,
        public ?string $speakingKey = null,
        public array $simplifiedVariants = [],
        public ?string $phraseId = null,
        public ?string $filler = null,
    ) {}

    public function isLearner(): bool
    {
        return $this->speaker === self::SPEAKER_LEARNER;
    }

    public function isQuestion(): bool
    {
        return str_ends_with(rtrim($this->textTarget), '?');
    }

    /** The same line with another text — what the server's assembly of a framed line produces. */
    public function withText(string $textTarget): self
    {
        return new self(
            $this->speaker, $this->roleTarget, $this->roleNative, $textTarget, $this->textNative,
            $this->pronunciationNative, $this->speakingKey, $this->simplifiedVariants, $this->phraseId, $this->filler,
        );
    }

    /**
     * The prompt's shape: A — speaker, roles and the two texts; B — the frame reference, the filler
     * and the speaking support besides.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! $this->isLearner()) {
            return [
                'speaker' => $this->speaker,
                'role_target' => $this->roleTarget,
                'role_native' => $this->roleNative,
                'text_target' => $this->textTarget,
                'text_native' => $this->textNative,
            ];
        }

        return [
            'speaker' => $this->speaker,
            'role_target' => $this->roleTarget,
            'role_native' => $this->roleNative,
            'phrase_id' => $this->phraseId,
            'filler' => $this->filler,
            'text_target' => $this->textTarget,
            'text_native' => $this->textNative,
            'pronunciation_native' => $this->pronunciationNative,
            'speaking_key' => $this->speakingKey,
            'simplified_variants' => $this->simplifiedVariants,
        ];
    }
}
