<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * «СЛОВА» (наряд SESSION-1a, разд. 1–2): three cards per word or chunk of the scene, in position order — meet it
 * (`word_intro`), say it (`word_repeat`), check it — spaced so that no word's three cards stand side by side
 * ({@see Spacing}).
 *
 * The check of a term of two words or more (articles aside) is `word_assemble` — putting a chunk together is what a
 * chunk is learnt by. A single word walks the cycle `word_choose` → `word_listen` → `word_in_line` from a place the
 * scene's seed picks, word after word, so a day does not check every word the same way and a day dealt again checks
 * them the same way. A check the word cannot have — no line of the day says it, no other word to offer against it —
 * becomes `word_choose`, the check every word can have (a Beginner's translations are topped up from the catalogue) —
 * unless even that has nothing to choose between (the only word of an Intermediate day): then the word is met and
 * said, and goes without a check; the spacing of the other words does not move.
 */
final readonly class WordsStage
{
    /** @var list<CardKind> the checks a single word rotates through */
    public const CHECKS = [CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine];

    public function __construct(private WordCards $cards = new WordCards) {}

    /**
     * @param  list<string>  $nativeTopUp  catalogue translations for the Beginner choice card when the day is too small
     * @return list<CardDraft>
     */
    public function build(SceneMaterial $scene, PlanLevel $level, array $nativeTopUp): array
    {
        $intro = [];
        $repeat = [];
        $check = [];
        $rotating = 0;
        foreach ($scene->vocabulary() as $term) {
            $intro[] = $this->cards->intro($scene, $term);
            $repeat[] = $this->cards->repeat($scene, $term);
            if ($this->cards->isMultiWord($scene, $term)) {
                $check[] = $this->cards->assemble($scene, $term);

                continue;
            }
            $kind = Rotation::pick($scene->seed('words:check'), $rotating++, self::CHECKS);
            $check[] = match ($kind) {
                CardKind::WordListen => $this->cards->listen($scene, $term),
                CardKind::WordInLine => $this->cards->inLine($scene, $term),
                default => null,
            } ?? $this->cards->choose($scene, $term, $level, $nativeTopUp);
        }

        return Spacing::interleave($intro, $repeat, $check);
    }

    /**
     * The card a word that failed twice comes back as: `word_choose`, in the direction of the plan's level; none when
     * its scene has nothing to choose between.
     *
     * @param  list<string>  $nativeTopUp
     */
    public function returned(SceneMaterial $scene, PlanTerm $term, PlanLevel $level, array $nativeTopUp): ?CardDraft
    {
        return $this->cards->choose($scene, $term, $level, $nativeTopUp);
    }
}
