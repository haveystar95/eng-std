<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\CardView;
use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Application\Dto\TermView;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * Cards for the wire: the stored payload plus what is resolved at READ time — the photo of the
 * term a card is about and the audio id of the partner's line — so a photo or a voice that
 * arrived after the day was dealt is on the card the next time it is read. Two queries for any
 * number of cards.
 */
final readonly class CardViews
{
    public function __construct(
        private PlanTermRepository $terms,
        private LineAudioStore $audios,
        private LineSpeaker $speaker,
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
        $voice = $this->speaker->voiceKeyFor($targetLang);
        $audios = $voice === null ? [] : $this->audios->forScenes(array_keys($sceneIds), $voice);

        return array_map(fn (DayCard $c): CardView => $this->card($c, $termsById, $audios), $cards);
    }

    /**
     * @param  array<string, PlanTerm>  $termsById
     * @param  array<string, LineAudioRow>  $audios
     */
    private function card(DayCard $card, array $termsById, array $audios): CardView
    {
        $payload = $card->payload();
        $termId = $payload['plan_term_id'] ?? null;
        if (is_string($termId) && isset($termsById[$termId])) {
            $payload['image'] = $termsById[$termId]->image()?->toArray();
        }
        $sceneId = $payload['scene_id'] ?? null;
        $step = $payload['exchange_step'] ?? null;
        if (is_string($sceneId) && is_int($step)) {
            $payload['audio_id'] = $audios[$sceneId.':'.$step]->id ?? null;
        }
        if (is_string($sceneId) && is_array($payload['exchanges'] ?? null)) {
            $payload['exchanges'] = array_map(static function (mixed $exchange) use ($audios, $sceneId): mixed {
                if (is_array($exchange) && is_int($exchange['step'] ?? null)) {
                    $exchange['audio_id'] = $audios[$sceneId.':'.$exchange['step']]->id ?? null;
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

    public static function term(PlanTerm $term): TermView
    {
        return new TermView(
            id: $term->id()->value,
            sceneId: $term->sceneId()->value,
            kind: $term->kind()->value,
            ref: $term->ref(),
            textTarget: $term->textTarget(),
            textNative: $term->textNative(),
            pronunciationNative: $term->pronunciationNative(),
            definitionTarget: $term->definitionTarget(),
            exampleTarget: $term->exampleTarget(),
            exampleNative: $term->exampleNative(),
            speakingKey: $term->speakingKey(),
            simplifiedVariants: $term->simplifiedVariants(),
            image: $term->image()?->toArray(),
        );
    }
}
