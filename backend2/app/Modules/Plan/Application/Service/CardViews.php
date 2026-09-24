<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\CardView;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Shared\Domain\ValueObject\Ulid;

/**
 * Cards for the wire: the stored payload plus what is resolved at READ time (наряд SESSION-1a, разд. 0, 5).
 *
 * A dealt payload holds no id and no address — a photo or a voice may arrive after the day is dealt — so every sound
 * of it is a stub (`{ref, voice, url, duration_ms}`, {@see \App\Modules\Plan\Domain\Assembly\Audio}) and every photo
 * slot of a word card `{url, tone}`. Here the stub gets the id of its file in its speaker's voice of that scene and the
 * file's length, the photo slot the word's photo and tone, and the whole visit played once (`listen_dialogue`,
 * `listen_review`) its length when every line of it has one. Found wherever they stand in the payload, not at named
 * places: a new kind with a sound in a new spot is resolved without a line here. Three queries for any number of
 * cards.
 *
 * A SOUND IS LOOKED UP IN THE SCENE IT STANDS IN (наряд FIX-4 §1): the scene of the nearest node around it that names
 * one (`scene_id`) — the card's own for most kinds, a page's for a card of several scenes. «Вспомнить» of the owner's
 * rehearsal played the files of «Ресепшен зала» on every line of its page «С тренером»: the lines' refs (`x1b`…) are
 * the same in both scenes, and the card's scene was the only one asked.
 */
final readonly class CardViews
{
    /** The kinds that play the whole visit and say how long it takes. */
    private const WHOLE_VISIT = [CardKind::ListenDialogue, CardKind::ListenReview];

    public function __construct(
        private PlanTermRepository $terms,
        private VoiceCasts $casts,
        private SceneVoices $voices,
    ) {}

    /**
     * @param  list<DayCard>  $cards
     * @param  array<string, int>  $dayNumbers  day id → its number, for the day a returned card failed on (`source_day`)
     * @return list<CardView>
     */
    public function forCards(array $cards, string $targetLang, array $dayNumbers = []): array
    {
        $sceneIds = [];
        $wordScenes = [];
        foreach ($cards as $card) {
            foreach (self::scenesIn($card->payload()) as $sceneId) {
                $sceneIds[$sceneId] = true;
            }
            $sceneId = self::sceneOf($card);
            if ($card->unitKind() === UnitKind::Word && Ulid::isValid($sceneId)) {
                $wordScenes[$sceneId] = PlanSceneId::fromString($sceneId);
            }
        }

        /** @var array<string, array<string, PlanTerm>> $termsByRef scene id → ref → term */
        $termsByRef = [];
        if ($wordScenes !== []) {
            foreach ($this->terms->forScenes(array_values($wordScenes)) as $sceneId => $terms) {
                foreach ($terms as $term) {
                    $termsByRef[(string) $sceneId][$term->ref()] = $term;
                }
            }
        }
        $sceneList = array_map('strval', array_keys($sceneIds));
        $audio = $sceneList === [] ? SceneAudioIndex::empty() : $this->voices->index($targetLang, $this->casts->ofScenes($sceneList));

        return array_map(
            fn (DayCard $c): CardView => $this->card($c, $termsByRef, $audio, $dayNumbers),
            $cards,
        );
    }

    /**
     * @param  array<string, array<string, PlanTerm>>  $termsByRef
     * @param  array<string, int>  $dayNumbers
     */
    private function card(DayCard $card, array $termsByRef, SceneAudioIndex $audio, array $dayNumbers): CardView
    {
        $term = $card->unitKind() === UnitKind::Word ? ($termsByRef[self::sceneOf($card)][$card->unitRef()] ?? null) : null;
        $payload = self::resolve($card->payload(), '', $card->unitKind() === UnitKind::Word, $term, $audio);
        if (in_array($card->kind(), self::WHOLE_VISIT, true) && array_key_exists('total_ms', $payload)) {
            $payload['total_ms'] = self::totalMs($payload['lines'] ?? null);
        }
        $sourceDayId = $card->sourceDayId()?->value;

        return new CardView(
            id: $card->id()->value,
            stage: $card->stage()->value,
            position: $card->position(),
            kind: $card->kind()->value,
            source: $card->source()->value,
            sourceDayId: $sourceDayId,
            unitKind: $card->unitKind()->value,
            unitRef: $card->unitRef(),
            payload: $payload,
            retryOf: $card->retryOf()?->value,
            result: $card->result()?->value,
            attempts: $card->attempts(),
            answeredAt: $card->answeredAt()?->format(DATE_ATOM),
            returns: $card->returns(),
            response: $card->response(),
            sourceDay: $sourceDayId === null ? null : ($dayNumbers[$sourceDayId] ?? null),
        );
    }

    /**
     * Every audio stub of a payload with its file's id and length — in the scene of the nearest node that names one,
     * `$sceneId` being the scene named above this node — and, on a word card, every photo slot with the word's photo and
     * tone. Nothing else is touched.
     *
     * @template K of array-key
     *
     * @param  array<K, mixed>  $value
     * @return array<K, mixed>
     */
    private static function resolve(array $value, string $sceneId, bool $wordCard, ?PlanTerm $term, SceneAudioIndex $audio): array
    {
        $own = $value['scene_id'] ?? null;
        if (is_string($own) && $own !== '') {
            $sceneId = $own;
        }
        if (self::isAudioStub($value)) {
            $row = $sceneId === '' ? null : $audio->rowOf($sceneId, (string) $value['ref']);
            $value['duration_ms'] = $row?->durationMs;
            $value['audio_id'] = $row?->id;

            return $value;
        }
        foreach ($value as $key => $item) {
            if (! is_array($item)) {
                continue;
            }
            $value[$key] = $key === 'image' && $wordCard && self::isPhotoSlot($item)
                ? ['url' => $term?->image()?->url, 'tone' => $term?->imageTone()]
                : self::resolve($item, $sceneId, $wordCard, $term, $audio);
        }

        return $value;
    }

    /**
     * The visit's length: the sum of its lines' lengths, or null while any line has none — a total that leaves a
     * line out would be a length the player never plays.
     */
    private static function totalMs(mixed $lines): ?int
    {
        if (! is_array($lines) || $lines === []) {
            return null;
        }
        $total = 0;
        foreach ($lines as $line) {
            $audio = is_array($line) ? ($line['audio'] ?? null) : null;
            $ms = is_array($audio) ? ($audio['duration_ms'] ?? null) : null;
            if (! is_int($ms)) {
                return null;
            }
            $total += $ms;
        }

        return $total;
    }

    /**
     * The four keys of a dealt stub, in any order — a payload read back from `jsonb` does not keep the order it was
     * written in.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function isAudioStub(array $value): bool
    {
        $keys = array_map('strval', array_keys($value));
        sort($keys);

        return $keys === ['duration_ms', 'ref', 'url', 'voice'] && is_string($value['ref']);
    }

    /** @param array<array-key, mixed> $value */
    private static function isPhotoSlot(array $value): bool
    {
        $keys = array_map('strval', array_keys($value));
        sort($keys);

        return $keys === ['tone', 'url'];
    }

    /** The card's own scene — whose words a word card's photo is looked up among. */
    private static function sceneOf(DayCard $card): string
    {
        $sceneId = $card->payload()['scene_id'] ?? null;

        return is_string($sceneId) ? $sceneId : '';
    }

    /**
     * Every scene a payload names, at any depth — the card's own and each page's: the voices and files of all of them
     * are read in one query.
     *
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    private static function scenesIn(array $value): array
    {
        $out = [];
        if (is_string($value['scene_id'] ?? null) && $value['scene_id'] !== '') {
            $out[] = $value['scene_id'];
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                $out = [...$out, ...self::scenesIn($item)];
            }
        }

        return array_values(array_unique($out));
    }
}
