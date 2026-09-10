<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/** «Говорю сам»: one card per exchange — the partner's line played, the learner's line said. */
final class SpeakStage
{
    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene): array
    {
        return array_map(fn (Exchange $e): CardDraft => $this->speak($scene, $e), $scene->completeExchanges());
    }

    public function speak(SceneMaterial $scene, Exchange $exchange): CardDraft
    {
        return new CardDraft(
            CardKind::Speak,
            UnitKind::Exchange,
            CardPayloads::exchangeRef($exchange->step),
            CardPayloads::speak($scene->sceneId, $exchange),
        );
    }
}
