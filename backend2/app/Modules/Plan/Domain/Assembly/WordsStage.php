<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * «Слова»: four cards per word — meet it, say it, choose it, put it into its example — dealt with
 * spacing: word 1 met, word 2 met, word 1 said, word 3 met, word 2 said, word 1 chosen…
 */
final class WordsStage
{
    /**
     * @param  list<string>  $extraTranslations
     * @return list<CardDraft>
     */
    public function build(SceneMaterial $scene, PlanLevel $level, array $extraTranslations): array
    {
        $words = $scene->vocabulary();
        $timed = [];
        foreach ($words as $index => $term) {
            $timed[] = [$index, 0, new CardDraft(CardKind::WordIntro, UnitKind::Word, $term->ref(), CardPayloads::wordIntro($scene->sceneId, $term))];
            $timed[] = [$index + 1, 1, new CardDraft(CardKind::WordSay, UnitKind::Word, $term->ref(), CardPayloads::wordSay($scene->sceneId, $term))];
            $timed[] = [$index + 2, 2, $this->choose($scene, $term, $level, $words, $extraTranslations)];
            $cloze = CardPayloads::wordCloze($scene->sceneId, $term, self::textsExcept($words, $term), $this->seed($scene, $term, 'cloze'));
            if ($cloze !== null) {
                $timed[] = [$index + 3, 3, new CardDraft(CardKind::WordCloze, UnitKind::Word, $term->ref(), $cloze)];
            }
        }
        usort($timed, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $row): CardDraft => $row[2], $timed);
    }

    /**
     * The card a returned word comes back as.
     *
     * @param  list<string>  $extraTranslations
     */
    public function returned(SceneMaterial $scene, PlanTerm $term, PlanLevel $level, array $extraTranslations): CardDraft
    {
        return $this->choose($scene, $term, $level, $scene->vocabulary(), $extraTranslations);
    }

    /**
     * @param  list<PlanTerm>  $words
     * @param  list<string>  $extraTranslations
     */
    private function choose(SceneMaterial $scene, PlanTerm $term, PlanLevel $level, array $words, array $extraTranslations): CardDraft
    {
        $byDefinition = $level === PlanLevel::Intermediate;
        $others = $byDefinition
            ? self::textsExcept($words, $term)
            : [...self::translationsExcept($words, $term), ...$extraTranslations];

        return new CardDraft(
            CardKind::WordChoose,
            UnitKind::Word,
            $term->ref(),
            CardPayloads::wordChoose($scene->sceneId, $term, $byDefinition, $others, $this->seed($scene, $term, 'choose')),
        );
    }

    /**
     * @param  list<PlanTerm>  $words
     * @return list<string>
     */
    private static function textsExcept(array $words, PlanTerm $except): array
    {
        return array_values(array_map(
            static fn (PlanTerm $t): string => $t->textTarget(),
            array_filter($words, static fn (PlanTerm $t): bool => ! $t->id()->equals($except->id())),
        ));
    }

    /**
     * @param  list<PlanTerm>  $words
     * @return list<string>
     */
    private static function translationsExcept(array $words, PlanTerm $except): array
    {
        return array_values(array_map(
            static fn (PlanTerm $t): string => $t->textNative(),
            array_filter($words, static fn (PlanTerm $t): bool => ! $t->id()->equals($except->id())),
        ));
    }

    private function seed(SceneMaterial $scene, PlanTerm $term, string $card): string
    {
        return $scene->sceneId->value.':'.$term->ref().':'.$card;
    }
}
