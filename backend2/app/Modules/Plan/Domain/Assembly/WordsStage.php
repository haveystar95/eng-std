<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;

/**
 * «СЛОВА» (наряд SESSION-1a, разд. 1–2; SESSION-1e): three cards per word or chunk of the scene, in position order —
 * meet it (`word_intro`), say it (`word_repeat`), check it — spaced so that no word's three cards stand side by side
 * ({@see Spacing}).
 *
 * The checks walk ONE circle over all the words — `word_choose` → `word_listen` → `word_in_line` → `word_assemble`, a
 * kind a word cannot have swapped with a word that can ({@see WordChecks}): a chunk is not always assembled, a single
 * word is not always chosen, and eight words get every kind twice. A word with no check it can have is met and said,
 * and goes without a check; the spacing of the other words does not move. The stage is the same at both levels — a
 * Beginner and an Intermediate day differ only in the phrases said aloud.
 */
final readonly class WordsStage
{
    private WordChecks $checks;

    public function __construct(private WordCards $cards = new WordCards)
    {
        $this->checks = new WordChecks($this->cards);
    }

    /**
     * @param  list<string>  $nativeTopUp  catalogue translations for a day of fewer than four words
     * @return list<CardDraft>
     */
    public function build(SceneMaterial $scene, array $nativeTopUp): array
    {
        $intro = [];
        $repeat = [];
        $check = [];
        foreach ($this->checks->deal($scene, $nativeTopUp) as ['term' => $term, 'check' => $card]) {
            $intro[] = $this->cards->intro($scene, $term);
            $repeat[] = $this->cards->repeat($scene, $term);
            $check[] = $card;
        }

        return Spacing::interleave($intro, $repeat, $check);
    }

    /**
     * The card a word that failed twice comes back as: `word_choose`, asked the way its scene's day asks it
     * ({@see WordChecks::chooseFor()}); none when its scene has nothing to choose between.
     *
     * @param  list<string>  $nativeTopUp
     */
    public function returned(SceneMaterial $scene, PlanTerm $term, array $nativeTopUp): ?CardDraft
    {
        return $this->checks->chooseFor($scene, $term, $nativeTopUp);
    }
}
