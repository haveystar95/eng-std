<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * One chat bubble of an exchange. Speaker A is the partner (audio only, no speaking support);
 * speaker B is the learner (pronunciation, a speaking key and simplified variants).
 */
final readonly class Message
{
    public const SPEAKER_PARTNER = 'A';

    public const SPEAKER_LEARNER = 'B';

    /**
     * @param  list<string>  $simplifiedVariants
     * @param  list<string>  $phraseIds
     * @param  list<string>  $vocabularyIds
     */
    public function __construct(
        public string $speaker,
        public string $roleTarget,
        public string $roleNative,
        public string $textTarget,
        public string $textNative,
        public ?string $pronunciationNative,
        public ?string $speakingKey,
        public array $simplifiedVariants,
        public array $phraseIds,
        public array $vocabularyIds,
    ) {}

    public function isLearner(): bool
    {
        return $this->speaker === self::SPEAKER_LEARNER;
    }

    public function isQuestion(): bool
    {
        return str_ends_with(rtrim($this->textTarget), '?');
    }

    /** @param list<string> $variants */
    public function withVariants(array $variants): self
    {
        return new self(
            $this->speaker, $this->roleTarget, $this->roleNative, $this->textTarget, $this->textNative,
            $this->pronunciationNative, $this->speakingKey, $variants, $this->phraseIds, $this->vocabularyIds,
        );
    }

    public function withoutPronunciation(): self
    {
        return new self(
            $this->speaker, $this->roleTarget, $this->roleNative, $this->textTarget, $this->textNative,
            null, $this->speakingKey, $this->simplifiedVariants, $this->phraseIds, $this->vocabularyIds,
        );
    }

    /** @param list<string> $ids */
    public function withVocabularyIds(array $ids): self
    {
        return new self(
            $this->speaker, $this->roleTarget, $this->roleNative, $this->textTarget, $this->textNative,
            $this->pronunciationNative, $this->speakingKey, $this->simplifiedVariants, $this->phraseIds, $ids,
        );
    }

    /** @param list<string> $ids */
    public function withPhraseIds(array $ids): self
    {
        return new self(
            $this->speaker, $this->roleTarget, $this->roleNative, $this->textTarget, $this->textNative,
            $this->pronunciationNative, $this->speakingKey, $this->simplifiedVariants, $ids, $this->vocabularyIds,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $row = [
            'speaker' => $this->speaker,
            'role_target' => $this->roleTarget,
            'role_native' => $this->roleNative,
            'text_target' => $this->textTarget,
            'text_native' => $this->textNative,
            'phrase_ids' => $this->phraseIds,
            'vocabulary_ids' => $this->vocabularyIds,
        ];
        if ($this->isLearner()) {
            $row['pronunciation_native'] = $this->pronunciationNative;
            $row['speaking_key'] = $this->speakingKey;
            $row['simplified_variants'] = $this->simplifiedVariants;
        }

        return $row;
    }
}
