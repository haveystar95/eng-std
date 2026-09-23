<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

/**
 * One line as the CLIENT receives it next to its sound (наряд ADM-1, доработка): the text the answer shows and the id of
 * the file the answer tells the phone to play — and that file's own text: the text the vendor was sent for it (when the
 * request log names it exactly) or else the lesson's text at the file's own scene and ref.
 */
final readonly class ServedSound
{
    public const CARDS = 'cards';

    public const ROOM = 'room';

    public function __construct(
        public int $day,
        /** `cards` — `GET …/days/{n}/cards`; `room` — `GET …/days/{n}`. */
        public string $answer,
        /** The card's id, or the room's section the line stands in. */
        public string $place,
        public string $kind,
        public string $path,
        public string $text,
        public string $audioId,
        /** `<scene>:<ref>` of the file the id names; null — not a file of this plan. */
        public ?string $fileRef,
        public ?string $fileText,
        /** `vendor` or `lesson`; null when the file's text is not known. */
        public ?string $fileTextSource,
        public ?string $voiceKey,
        /** An option, a chip or a filler — a fragment the sound says within its phrase, not a line of its own. */
        public bool $fragment = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'day' => $this->day,
            'answer' => $this->answer,
            'place' => $this->place,
            'kind' => $this->kind,
            'path' => $this->path,
            'text' => $this->text,
            'audio_id' => $this->audioId,
            'file_ref' => $this->fileRef,
            'file_text' => $this->fileText,
            'file_text_source' => $this->fileTextSource,
            'voice_key' => $this->voiceKey,
            'fragment' => $this->fragment,
        ];
    }
}
