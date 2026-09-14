<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\UnitState;

/**
 * WHERE EACH UNIT OF THE DAY STANDS (DAY-UI-2) — a word, a phrase or an exchange, read over all of
 * its cards in the day: a card failed twice (its `returns`) → `returns_tomorrow`, whatever else
 * happened; every card answered → `done` (a skip is an answer); otherwise `pending`.
 *
 * The dialogue read is not a unit card. It carries every exchange and is filed under the first
 * one's reference, and reading the dialogue does not walk exchange one — counting it would mark the
 * first line «пройдено» while its own practice has not begun.
 */
final class UnitStates
{
    /**
     * @param  list<DayCard>  $cards
     * @return array<string, UnitState> keyed by {@see key()}
     */
    public static function of(array $cards): array
    {
        $answered = [];
        $returns = [];
        foreach ($cards as $card) {
            if ($card->kind() === CardKind::DialogueRead) {
                continue;
            }
            $key = self::key(self::sceneOf($card), $card->unitKind(), $card->unitRef());
            $answered[$key] = ($answered[$key] ?? true) && $card->isAnswered();
            $returns[$key] = ($returns[$key] ?? false) || $card->returns();
        }

        $out = [];
        foreach ($answered as $key => $all) {
            $out[$key] = match (true) {
                $returns[$key] => UnitState::ReturnsTomorrow,
                $all => UnitState::Done,
                default => UnitState::Pending,
            };
        }

        return $out;
    }

    public static function key(string $sceneId, UnitKind $kind, string $ref): string
    {
        return $sceneId.':'.$kind->value.':'.$ref;
    }

    public static function sceneOf(DayCard $card): string
    {
        $sceneId = $card->payload()['scene_id'] ?? null;

        return is_string($sceneId) ? $sceneId : '';
    }
}
