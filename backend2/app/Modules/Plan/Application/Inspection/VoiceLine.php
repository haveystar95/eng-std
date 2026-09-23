<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedAudio;

/** One line a scene says, the voice its cast gives it, and what is stored for it (наряд ADM-1). */
final readonly class VoiceLine
{
    public const PARTNER_LINE = 'partner_line';

    public const LEARNER_LINE = 'learner_line';

    public const PHRASE = 'phrase';

    public const FILLER = 'filler';

    public const WORD = 'word';

    /** In the cast's voice — the reader finds it. */
    public const VOICED = 'voiced';

    /** No file in the cast's voice — the phone reads it. */
    public const PHONE = 'phone';

    /** The pack has no voice for this speaker — nothing could be bought. */
    public const NONE = 'none';

    /**
     * @param  list<InspectedAudio>  $others  files of this line in other voices
     * @param  array<string, string>  $identities  voice key → `role:gender` by the pack's config
     */
    public function __construct(
        public string $ref,
        public string $kind,
        public string $speaker,
        public string $text,
        public string $gender,
        public string $genderRule,
        public ?string $expectedVoice,
        public ?InspectedAudio $audio,
        public array $others,
        public array $identities,
        public ?string $voicedText,
    ) {}

    public function status(): string
    {
        return match (true) {
            $this->expectedVoice === null => self::NONE,
            $this->audio !== null => self::VOICED,
            default => self::PHONE,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ref' => $this->ref,
            'kind' => $this->kind,
            'speaker' => $this->speaker,
            'text' => $this->text,
            'gender' => $this->gender,
            'rule' => $this->genderRule,
            'voice' => self::voice($this->expectedVoice, $this->identities),
            'status' => $this->status(),
            'audio' => $this->audio === null ? null : self::audio($this->audio),
            'voiced_text' => $this->voicedText,
            'other_voices' => array_map(fn (InspectedAudio $a): array => [
                'voice' => self::voice($a->voiceKey, $this->identities),
                'audio' => self::audio($a),
            ], $this->others),
        ];
    }

    /**
     * A voice key as the page names it: the pack's role and gender for it, the model and a short id — «ученик · male ·
     * TWut…».
     *
     * @param  array<string, string>  $identities
     * @return array<string, string|null>|null
     */
    public static function voice(?string $key, array $identities): ?array
    {
        if ($key === null) {
            return null;
        }
        $parts = explode(':', $key);
        $identity = $identities[$key] ?? null;
        $id = $parts[2] ?? '';

        return [
            'key' => $key,
            'provider' => $parts[0],
            'model' => $parts[1] ?? null,
            'voice_id' => $id,
            'short_id' => $id === '' ? null : mb_substr($id, 0, 4).'…',
            'identity' => $identity,
        ];
    }

    /** @return array<string, mixed> */
    private static function audio(InspectedAudio $audio): array
    {
        return [
            'id' => $audio->id,
            'characters' => $audio->characters,
            'credits' => $audio->credits,
            'cost_usd' => $audio->costUsd,
            'duration_ms' => $audio->durationMs,
            'bytes' => $audio->bytes,
            'request_id' => $audio->requestId,
            'created_at' => $audio->createdAt?->format(DATE_ATOM),
        ];
    }
}
