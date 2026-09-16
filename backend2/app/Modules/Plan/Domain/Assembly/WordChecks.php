<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\CardKind;

/**
 * THE CHECKS OF THE DAY'S WORDS (наряд SESSION-1e — общий круг, без привилегии сборки): which check every word of the
 * day gets and which way a `word_choose` asks — the one place that decides it, for the day and for a word that comes
 * back.
 *
 * - ONE CIRCLE `word_choose` → `word_listen` → `word_in_line` → `word_assemble` over all the words in their order: a word
 *   gets the kind the circle points at by its index, from a start the scene's seed picks — so eight words get every
 *   kind twice. A kind no word of the day can have (no chunk to assemble, no line to find a word in) is not in the
 *   circle, and the others share the words evenly;
 * - A KIND THE WORD CANNOT HAVE — an assembly of one word, a word in a line no line of the day says, a choice with
 *   nothing to choose between — is SWAPPED with the nearest word (by index; between two as near, the next one) that can
 *   have it and whose own kind this word can have: the count of every kind stays (решение архитектора 16.09, наряд
 *   SESSION-1e). No such word — the next kind round the circle the word can have; none at all — the word goes without a
 *   check;
 * - `word_choose` ASKS BOTH WAYS AT ANY LEVEL (a Beginner and an Intermediate day differ only in what is said aloud):
 *   `term_to_native` and `native_to_term` alternate over the day's choices, word after word, from a direction the
 *   scene's seed picks; a direction a choice cannot be made in gives way to the other.
 */
final readonly class WordChecks
{
    /** @var list<CardKind> the checks of the day's words, round */
    public const CYCLE = [CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine, CardKind::WordAssemble];

    /** @var list<string> the directions `word_choose` alternates */
    public const DIRECTIONS = [WordCards::TERM_TO_NATIVE, WordCards::NATIVE_TO_TERM];

    public function __construct(private WordCards $cards = new WordCards) {}

    /**
     * The check of every word of the day, in the words' order — null for a word that has none.
     *
     * @param  list<string>  $nativeTopUp  catalogue translations for a day of fewer than four words
     * @return list<array{term: PlanTerm, check: CardDraft|null}>
     */
    public function deal(SceneMaterial $scene, array $nativeTopUp): array
    {
        $words = $scene->vocabulary();
        $kinds = self::kinds($this->fits($scene, $words, $nativeTopUp), $scene->seed('words:check'));

        $out = [];
        $chosen = 0;
        foreach ($words as $i => $term) {
            $check = match ($kinds[$i]) {
                CardKind::WordChoose => $this->choose($scene, $term, $chosen++, $nativeTopUp),
                CardKind::WordListen => $this->cards->listen($scene, $term, $nativeTopUp),
                CardKind::WordInLine => $this->cards->inLine($scene, $term),
                CardKind::WordAssemble => $this->cards->assemble($scene, $term),
                default => null,
            };
            $out[] = ['term' => $term, 'check' => $check];
        }

        return $out;
    }

    /**
     * The `word_choose` of one word of the scene — a word that comes back: asked the way the day asks it, or, when the
     * day checks it otherwise, the way the next choice of the day after the ones before it would be asked. Null when
     * there is nothing to choose between.
     *
     * @param  list<string>  $nativeTopUp
     */
    public function chooseFor(SceneMaterial $scene, PlanTerm $term, array $nativeTopUp): ?CardDraft
    {
        $words = $scene->vocabulary();
        $kinds = self::kinds($this->fits($scene, $words, $nativeTopUp), $scene->seed('words:check'));
        $before = 0;
        foreach ($words as $i => $word) {
            if ($word->ref() === $term->ref()) {
                break;
            }
            if ($kinds[$i] === CardKind::WordChoose) {
                $before++;
            }
        }

        return $this->choose($scene, $term, $before, $nativeTopUp);
    }

    /**
     * THE CIRCLE, on what each word can have: the kind of every word, in the words' order — null for a word that can
     * have none. Pure: `$fits[$i]` holds the kinds (by value) word `$i` can have, `$seed` picks where the circle starts.
     *
     * @param  list<array<string, true>>  $fits
     * @return list<CardKind|null>
     */
    public static function kinds(array $fits, string $seed): array
    {
        $circle = array_values(array_filter(
            self::CYCLE,
            static fn (CardKind $kind): bool => array_filter($fits, static fn (array $can): bool => isset($can[$kind->value])) !== [],
        ));
        $count = count($fits);
        if ($circle === []) {
            return array_fill(0, $count, null);
        }

        /** @var list<CardKind|null> $kinds */
        $kinds = [];
        for ($i = 0; $i < $count; $i++) {
            $kinds[] = Rotation::pick($seed, $i, $circle);
        }
        for ($i = 0; $i < $count; $i++) {
            $kind = $kinds[$i];
            if ($kind === null || isset($fits[$i][$kind->value])) {
                continue;
            }
            $with = self::swapWith($fits, $kinds, $i);
            if ($with !== null) {
                $kinds[$i] = $kinds[$with];
                $kinds[$with] = $kind;

                continue;
            }
            $kinds[$i] = self::nextFitting($circle, $kind, $fits[$i]);
        }

        return array_values($kinds);
    }

    /**
     * The nearest word to swap kinds with: it can have word `$i`'s kind, and word `$i` can have its kind — nearest by
     * index, between two as near the next one; null when none can.
     *
     * @param  list<array<string, true>>  $fits
     * @param  array<int, CardKind|null>  $kinds
     */
    private static function swapWith(array $fits, array $kinds, int $i): ?int
    {
        $kind = $kinds[$i];
        $count = count($fits);
        for ($distance = 1; $distance < $count; $distance++) {
            foreach ([$i + $distance, $i - $distance] as $j) {
                $other = $kinds[$j] ?? null;
                if ($kind !== null && $other !== null && isset($fits[$j][$kind->value], $fits[$i][$other->value])) {
                    return $j;
                }
            }
        }

        return null;
    }

    /**
     * The next kind round the circle after `$kind` that the word can have; null when it can have none.
     *
     * @param  list<CardKind>  $circle
     * @param  array<string, true>  $can
     */
    private static function nextFitting(array $circle, CardKind $kind, array $can): ?CardKind
    {
        $at = (int) array_search($kind, $circle, true);
        for ($step = 1; $step < count($circle); $step++) {
            $next = $circle[($at + $step) % count($circle)];
            if (isset($can[$next->value])) {
                return $next;
            }
        }

        return null;
    }

    /**
     * What each word can have: a check whose card is made — an assembly only of a term of two words or more.
     *
     * @param  list<PlanTerm>  $words
     * @param  list<string>  $nativeTopUp
     * @return list<array<string, true>>
     */
    private function fits(SceneMaterial $scene, array $words, array $nativeTopUp): array
    {
        $fits = [];
        foreach ($words as $term) {
            $can = [];
            if ($this->cards->choose($scene, $term, WordCards::TERM_TO_NATIVE, $nativeTopUp) !== null
                || $this->cards->choose($scene, $term, WordCards::NATIVE_TO_TERM, $nativeTopUp) !== null) {
                $can[CardKind::WordChoose->value] = true;
            }
            if ($this->cards->listen($scene, $term, $nativeTopUp) !== null) {
                $can[CardKind::WordListen->value] = true;
            }
            if ($this->cards->inLine($scene, $term) !== null) {
                $can[CardKind::WordInLine->value] = true;
            }
            if ($this->cards->isMultiWord($scene, $term)) {
                $can[CardKind::WordAssemble->value] = true;
            }
            $fits[] = $can;
        }

        return $fits;
    }

    /**
     * The `word_choose` that is the day's choice number `$ordinal` (from 0): asked the way the alternation says, or the
     * other way when it cannot be made so.
     *
     * @param  list<string>  $nativeTopUp
     */
    private function choose(SceneMaterial $scene, PlanTerm $term, int $ordinal, array $nativeTopUp): ?CardDraft
    {
        $direction = Rotation::pick($scene->seed('words:direction'), $ordinal, self::DIRECTIONS);
        $other = $direction === WordCards::TERM_TO_NATIVE ? WordCards::NATIVE_TO_TERM : WordCards::TERM_TO_NATIVE;

        return $this->cards->choose($scene, $term, $direction, $nativeTopUp)
            ?? $this->cards->choose($scene, $term, $other, $nativeTopUp);
    }
}
