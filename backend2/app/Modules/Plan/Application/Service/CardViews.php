<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\CardView;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * Cards for the wire: the stored payload plus what is resolved at READ time — the photo of the
 * term a card is about and the audio id of the partner's line (in the partner's voice of that scene,
 * DAY-UI-3) — so a photo or a voice that arrived after the day was dealt is on the card the next time
 * it is read. Three queries for any number of cards.
 */
final readonly class CardViews
{
    public function __construct(
        private PlanTermRepository $terms,
        private SceneLocator $scenes,
        private SceneVoices $voices,
    ) {}

    /**
     * @param  list<DayCard>  $cards
     * @return list<CardView>
     */
    public function forCards(array $cards, string $targetLang): array
    {
        $sceneIds = [];
        foreach ($cards as $card) {
            $sceneId = $card->payload()['scene_id'] ?? null;
            if (is_string($sceneId)) {
                $sceneIds[$sceneId] = PlanSceneId::fromString($sceneId);
            }
        }

        $termsById = [];
        foreach ($this->terms->forScenes(array_values($sceneIds)) as $terms) {
            foreach ($terms as $term) {
                $termsById[$term->id()->value] = $term;
            }
        }
        $audio = $this->voices->index($targetLang, $this->scenes->voiceCastsOf(array_map('strval', array_keys($sceneIds))));

        return array_map(fn (DayCard $c): CardView => $this->card($c, $termsById, $audio), $cards);
    }

    /** @param array<string, PlanTerm> $termsById */
    private function card(DayCard $card, array $termsById, SceneAudioIndex $audio): CardView
    {
        $payload = $card->payload();
        $termId = $payload['plan_term_id'] ?? null;
        if (is_string($termId) && isset($termsById[$termId])) {
            $payload['image'] = $termsById[$termId]->image()?->toArray();
        }
        $sceneId = $payload['scene_id'] ?? null;
        $step = $payload['exchange_step'] ?? null;
        if (is_string($sceneId) && is_int($step)) {
            $payload['audio_id'] = $audio->idOf($sceneId, SpokenLines::partnerRef($step));
        }
        if (is_string($sceneId) && is_array($payload['exchanges'] ?? null)) {
            $payload['exchanges'] = array_map(static function (mixed $exchange) use ($audio, $sceneId): mixed {
                if (is_array($exchange) && is_int($exchange['step'] ?? null)) {
                    $exchange['audio_id'] = $audio->idOf($sceneId, SpokenLines::partnerRef($exchange['step']));
                }

                return $exchange;
            }, $payload['exchanges']);
        }

        return new CardView(
            id: $card->id()->value,
            stage: $card->stage()->value,
            position: $card->position(),
            kind: $card->kind()->value,
            source: $card->source()->value,
            sourceDayId: $card->sourceDayId()?->value,
            unitKind: $card->unitKind()->value,
            unitRef: $card->unitRef(),
            payload: $payload,
            retryOf: $card->retryOf()?->value,
            result: $card->result()?->value,
            attempts: $card->attempts(),
            answeredAt: $card->answeredAt()?->format(DATE_ATOM),
            returns: $card->returns(),
        );
    }
}
