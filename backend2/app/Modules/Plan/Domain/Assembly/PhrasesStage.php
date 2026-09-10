<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/** «Фразы»: meet it, repeat it aloud, assemble it from tiles — spaced the way the words are. */
final class PhrasesStage
{
    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene): array
    {
        $timed = [];
        foreach ($scene->phrases() as $index => $phrase) {
            $timed[] = [$index, 0, new CardDraft(CardKind::PhraseIntro, UnitKind::Phrase, $phrase->ref(), CardPayloads::phraseIntro($scene->sceneId, $phrase))];
            $timed[] = [$index + 1, 1, new CardDraft(CardKind::PhraseRepeat, UnitKind::Phrase, $phrase->ref(), CardPayloads::phraseRepeat($scene->sceneId, $phrase))];
            $timed[] = [$index + 2, 2, $this->assemble($scene, $phrase)];
        }
        usort($timed, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $row): CardDraft => $row[2], $timed);
    }

    public function returned(SceneMaterial $scene, PlanTerm $phrase): CardDraft
    {
        return $this->assemble($scene, $phrase);
    }

    private function assemble(SceneMaterial $scene, PlanTerm $phrase): CardDraft
    {
        $dayWords = array_map(static fn (PlanTerm $t): string => $t->textTarget(), $scene->vocabulary());

        return new CardDraft(
            CardKind::PhraseAssemble,
            UnitKind::Phrase,
            $phrase->ref(),
            CardPayloads::phraseAssemble($scene->sceneId, $phrase, $dayWords, $scene->sceneId->value.':'.$phrase->ref().':assemble'),
        );
    }
}
