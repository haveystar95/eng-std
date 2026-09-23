<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Query\GetDayCards;
use App\Modules\Plan\Application\Query\GetDayCardsHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * EVERY LINE THE CLIENT IS GIVEN WITH A SOUND, AND WHAT THAT SOUND SAYS (наряд ADM-1, доработка). Not the cards as
 * stored: a stored card holds a sound STUB (`{ref, voice}`), and which file it plays is decided when the card is READ
 * ({@see \App\Modules\Plan\Application\Service\CardViews}). So this reads the very answers the phone gets — the day's
 * cards (`GET …/days/{n}/cards`, «Вспомнить» among them) and the day (`GET …/days/{n}`) — through the same read-only
 * queries, as the plan's owner, and pairs each line's text with the file its sound id names: that file's scene and
 * ref, and its text (what the vendor was sent for it when the request log says so exactly, else the lesson's line at
 * the file's own scene and ref).
 *
 * Nothing here writes: both queries only read (the day not dealt yet is the dealer's outline, never stored).
 */
final readonly class ServedSounds
{
    public function __construct(
        private GetDayCardsHandler $cards,
        private GetDayRoomHandler $rooms,
        private VoiceTable $voices,
    ) {}

    /**
     * @return array{sounds: list<ServedSound>, unavailable: string|null}
     */
    public function of(PlanInspectionData $data): array
    {
        if ($data->plan->status() === PlanStatus::Deleted) {
            return ['sounds' => [], 'unavailable' => 'план удалён — клиенту он больше не отдаётся, ответ дня не собрать'];
        }
        $files = $this->files($data);
        $owner = new UserId($data->row->userId);

        $out = [];
        foreach ($data->days() as $day) {
            $dealt = $day->openedAt() !== null;
            foreach ($this->answers($data, $day, $owner) as [$answer, $document]) {
                $this->walk($document, $answer, $day->number(), $files, $out, $answer === ServedSound::ROOM && $dealt);
            }
        }

        return ['sounds' => $out, 'unavailable' => null];
    }

    /**
     * The day's two answers as plain arrays: its cards (a day not opened has none — its cards are not dealt) and its room.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function answers(PlanInspectionData $data, PlanDay $day, UserId $owner): array
    {
        $out = [];
        if ($day->openedAt() !== null) {
            $out[] = [ServedSound::CARDS, self::plain(($this->cards)(new GetDayCards($data->plan->id(), $day->number(), $owner)))];
        }
        $out[] = [ServedSound::ROOM, self::plain(($this->rooms)(new GetDayRoom($data->plan->id(), $day->number(), $owner)))];

        return $out;
    }

    /**
     * Every stored file of the plan by id: its `<scene>:<ref>`, voice, and text.
     *
     * @return array<string, array{ref: string, voice: string, text: string|null, source: string|null}>
     */
    private function files(PlanInspectionData $data): array
    {
        $lessonTexts = [];
        $vendorTexts = [];
        foreach ($data->scenes as $row) {
            $scene = $data->scene($row->id);
            if ($scene === null) {
                continue;
            }
            foreach ($this->voices->of($data, $scene) as $line) {
                $lessonTexts[$row->id][$line->ref] = $line->text;
                if ($line->audio !== null && $line->voicedText !== null) {
                    $vendorTexts[$line->audio->id] = $line->voicedText;
                }
            }
        }

        $out = [];
        foreach ($data->audios() as $audio) {
            $vendor = $vendorTexts[$audio->id] ?? null;
            $lesson = $lessonTexts[$audio->sceneId][$audio->lineRef] ?? null;
            $out[$audio->id] = [
                'ref' => $audio->sceneId.':'.$audio->lineRef,
                'voice' => $audio->voiceKey,
                'text' => $vendor ?? $lesson,
                'source' => $vendor !== null ? 'vendor' : ($lesson !== null ? 'lesson' : null),
            ];
        }

        return $out;
    }

    /**
     * Finds every line with a sound in an answer: a card's `audio` stub (resolved: `audio_id`) beside the node's
     * `text_target` — or, for a map of stubs (`audio: {term, line}`), beside the text of the node it names — and a
     * room's view with `audioId` beside its `text` (a line, a phrase, a usage) or `term` (a word).
     *
     * @param  array<mixed>  $node
     * @param  array<string, array{ref: string, voice: string, text: string|null, source: string|null}>  $files
     * @param  list<ServedSound>  $out
     */
    private function walk(array $node, string $answer, int $day, array $files, array &$out, bool $skipCards, string $path = '', ?string $place = null, ?string $kind = null): void
    {
        if (is_string($node['kind'] ?? null) && is_array($node['payload'] ?? null) && is_string($node['id'] ?? null)) {
            $place = $node['id'];
            $kind = $node['kind'];
        }
        // The room carries the day's cards too; those are read once — from the cards' own answer when the day has one.
        $pairs = ! ($skipCards && $place !== null);
        $audio = $pairs ? ($node['audio'] ?? null) : null;
        if (is_array($audio)) {
            if (array_key_exists('audio_id', $audio)) {
                $this->pair(self::textOf($node), $audio['audio_id'], $answer, $day, $place, $kind, $path, $files, $out);
            } else {
                foreach ($audio as $key => $stub) {
                    if (is_array($stub) && array_key_exists('audio_id', $stub)) {
                        $this->pair(self::textFor($node, (string) $key), $stub['audio_id'], $answer, $day, $place, $kind, $path.'.audio.'.$key, $files, $out);
                    }
                }
            }
        }
        if ($pairs && is_string($node['audioId'] ?? null)) {
            $text = is_string($node['text'] ?? null) ? $node['text'] : (is_string($node['term'] ?? null) ? $node['term'] : null);
            $this->pair($text, $node['audioId'], $answer, $day, $place ?? 'room', $kind ?? 'room', $path, $files, $out);
        }
        foreach ($node as $key => $child) {
            if (is_array($child) && $key !== 'audio') {
                $this->walk($child, $answer, $day, $files, $out, $skipCards, ltrim($path.'.'.$key, '.'), $place, $kind);
            }
        }
    }

    /**
     * @param  array<string, array{ref: string, voice: string, text: string|null, source: string|null}>  $files
     * @param  list<ServedSound>  $out
     */
    private function pair(?string $text, mixed $audioId, string $answer, int $day, ?string $place, ?string $kind, string $path, array $files, array &$out): void
    {
        if ($text === null || trim($text) === '' || ! is_string($audioId) || $audioId === '') {
            return;
        }
        $file = $files[$audioId] ?? null;
        $out[] = new ServedSound(
            day: $day,
            answer: $answer,
            place: $place ?? $answer,
            kind: $kind ?? $answer,
            path: $path === '' ? '.' : $path,
            text: $text,
            audioId: $audioId,
            fileRef: $file['ref'] ?? null,
            fileText: $file['text'] ?? null,
            fileTextSource: $file['source'] ?? null,
            voiceKey: $file['voice'] ?? null,
            fragment: preg_match('/(^|\.)(options|chips|fillers)\.\d+$/', $path) === 1,
        );
    }

    /** @param array<mixed> $node */
    private static function textOf(array $node): ?string
    {
        foreach (['text_target', 'text'] as $key) {
            if (is_string($node[$key] ?? null)) {
                return $node[$key];
            }
        }

        return null;
    }

    /**
     * The text a named stub of an `audio` map voices: `term` — the node's term, `line` — the line the term is used in.
     *
     * @param  array<mixed>  $node
     */
    private static function textFor(array $node, string $key): ?string
    {
        $named = $node[$key] ?? null;
        if (is_array($named)) {
            return self::textOf($named);
        }
        if ($key === 'line' && is_array($node['used_in'] ?? null)) {
            return self::textOf($node['used_in']);
        }

        return null;
    }

    /**
     * An answer's DTO as the plain array its JSON is made from.
     *
     * @return array<string, mixed>
     */
    private static function plain(object $view): array
    {
        $decoded = json_decode((string) json_encode($view), true);

        return is_array($decoded) ? $decoded : [];
    }
}
