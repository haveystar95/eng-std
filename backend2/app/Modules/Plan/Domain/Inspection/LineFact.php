<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * One line a scene says out loud and its sound. `expectedVoice` — the voice key the cast gives it (null: the language pack
 * has no such voice); `storedVoices` — the keys it is stored under, each with the speaker role and gender the pack files
 * that key as (`partner:female`), or null for a key the pack does not know; `voicedText` — the text the vendor was sent
 * for the file in the expected voice, when the request log names it exactly, else null.
 */
final readonly class LineFact
{
    /**
     * @param  list<int>  $days
     * @param  array<string, string|null>  $storedVoices  voice key → `role:gender` or null
     */
    public function __construct(
        public string $sceneId,
        public array $days,
        public string $ref,
        public string $speaker,
        public string $text,
        public string $expectedGender,
        public ?string $expectedVoice,
        public array $storedVoices,
        public ?string $voicedText,
    ) {}

    public function isVoiced(): bool
    {
        return $this->expectedVoice !== null && array_key_exists($this->expectedVoice, $this->storedVoices);
    }

    /** Stored in the voice its speaker has for the OTHER gender — bought for the wrong person. */
    public function hasOtherGenderVoice(): bool
    {
        foreach ($this->storedVoices as $identity) {
            if ($identity !== null && str_starts_with($identity, $this->speaker.':') && $identity !== $this->speaker.':'.$this->expectedGender) {
                return true;
            }
        }

        return false;
    }

    public function firstDay(): ?int
    {
        return $this->days === [] ? null : min($this->days);
    }
}
