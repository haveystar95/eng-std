<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/** «Диалог»: one card with every exchange in order; Intermediate reads it with the translations folded. */
final class DialogueStage
{
    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene, PlanLevel $level): array
    {
        $exchanges = $scene->lesson->exchanges;
        if ($exchanges === []) {
            return [];
        }

        return [new CardDraft(
            CardKind::DialogueRead,
            UnitKind::Exchange,
            CardPayloads::exchangeRef($exchanges[0]->step),
            CardPayloads::dialogueRead($scene->sceneId, $exchanges, $level === PlanLevel::Intermediate),
        )];
    }
}
