<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

/**
 * «СЛУШАЮ И ОТВЕЧАЮ» (наряд SESSION-1a, разд. 1–2; кадры 34-1…34-8): the whole visit by ear, then what was heard.
 *
 * In this order: the dialogue once without text (`listen_dialogue`), every listening question of the lesson
 * (`listen_question`, three to five), the dialogue again with its texts and where the answers were
 * (`listen_review`), a guess at the partner's answer on every ask exchange (`listen_predict`), the longest short
 * partner line at two tempos (`listen_pace`) and the number or time of the visit (`listen_number`) — eight to eleven
 * cards on a clean lesson. A card whose material the lesson lacks is left out, the rest keep their order.
 * `listen_pairs` is reserved and never dealt: the lesson has no two similar lines to pair (v4.6).
 */
final class ListenStage
{
    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene): array
    {
        $cards = [ListenCards::dialogue($scene)];

        $asked = [];
        foreach (array_keys($scene->lesson->listening) as $index) {
            $question = ListenCards::question($scene, $index);
            if ($question !== null) {
                $asked[] = $index;
                $cards[] = $question;
            }
        }
        $cards[] = ListenCards::review($scene, $asked);

        // Only a complete ask exchange has a guess: the card itself says which ({@see ListenCards::predict()}).
        foreach ($scene->lesson->exchanges as $exchange) {
            $cards[] = ListenCards::predict($scene, $exchange);
        }
        $cards[] = ListenCards::pace($scene);
        $cards[] = ListenCards::number($scene);

        return array_values(array_filter($cards, static fn (?CardDraft $card): bool => $card !== null));
    }
}
