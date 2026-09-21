<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\Service\SpokenWordBoundary;

/**
 * DID THE LEARNER USE THE PHRASE — THE WAY A PERSON WOULD SAY IT (наряд BACK-TAILS-2 §2).
 *
 * The talk's targets (`targets[].said`, кадр 37-5 and the strip of the ribbon) used to be ticked by the card's rule in its
 * `free` mode over the frame's words outside the window, and the owner's gym talk of 21.09 came to «0 фраз из 7» with
 * «Weekdays works for me» and «Yes it is my first visit» said out loud. A phrase is USED when the learner said its
 * meaning in its key words; the order of the words, the words around them and the form each is said in do not matter.
 *
 * (а) Both sides are brought to one form — case, punctuation, contractions spelt out, abbreviations to their letters,
 *     numbers written one way ({@see SpeechMatch::words()}) — and the words that carry no content of their own go: the
 *     pack's `unstressed_words` and `articles`. What is left of the TARGET are its key words. A target of nothing but
 *     such words is asked for whole.
 * (б) A key word is found by its BASE ({@see bases()}): the pack's regular endings and its table of irregular forms
 *     (`inflection_rules`, `irregular_forms`); a language without the table compares its words exactly.
 * (в) SAID when every key word of the target is in what was heard, in any order, each heard word spent once — one may be
 *     missing when the target has {@see LENIENT_FROM} key words or more.
 * (г) THE MODEL'S WORD is the second support, never the first: a target the rule did not find, which the role named in
 *     its `phrases_used`, counts when the heard line holds at least {@see VOUCHED} of its key words — so a model that
 *     heard a phrase nobody said credits nothing.
 * (д) What is said stays said: a move is read only for the targets not yet said, and for all of them — not only for the
 *     phrases of the scene the move stands in.
 *
 * This is the TALK's rule. The cards keep theirs — {@see SpeechMatch} and the slot judge — and nothing here reaches them.
 */
final readonly class PhraseUse
{
    /** From this many key words on, one of them may go missing and the phrase is still said. */
    public const LENIENT_FROM = 4;

    /** The share of a target's key words the heard line must hold for the model's word to count (rule г). */
    public const VOUCHED = 0.5;

    /** 1 of 2 is exactly a half, and a float says otherwise. */
    private const EPSILON = 1e-9;

    public function __construct(
        private SpeechMatch $speech = new SpeechMatch,
        private SpokenWordBoundary $boundary = new SpokenWordBoundary,
    ) {}

    /**
     * The targets a move says, of those not said yet (rules в, г, д).
     *
     * @param  list<ConversationPhrase>  $unsaid  the talk's targets not said so far
     * @param  list<string>  $modelSays  the ids the role named in its `phrases_used` for this move
     * @return list<string> the ids ({@see ConversationPhrase::id()}) of the targets this move says
     */
    public function heardIn(string $heard, array $unsaid, array $modelSays, LanguagePack $pack): array
    {
        if (trim($heard) === '') {
            return [];
        }
        $named = array_fill_keys($modelSays, true);
        $out = [];
        foreach ($unsaid as $phrase) {
            if ($this->said($heard, $phrase->textTarget, $pack)
                || (isset($named[$phrase->id()]) && $this->vouched($heard, $phrase->textTarget, $pack))) {
                $out[] = $phrase->id();
            }
        }

        return array_values(array_unique($out));
    }

    /** Rule (в): every key word of the target heard, one forgiven from {@see LENIENT_FROM} on. */
    public function said(string $heard, string $target, LanguagePack $pack): bool
    {
        ['found' => $found, 'total' => $total] = $this->tally($heard, $target, $pack);

        return $total > 0 && $found >= $total - ($total >= self::LENIENT_FROM ? 1 : 0);
    }

    /** Rule (г), the heard side of it: at least {@see VOUCHED} of the target's key words were heard. */
    public function vouched(string $heard, string $target, LanguagePack $pack): bool
    {
        ['found' => $found, 'total' => $total] = $this->tally($heard, $target, $pack);

        return $total > 0 && $found / $total + self::EPSILON >= self::VOUCHED;
    }

    /**
     * How many of the target's key words the heard line holds, each heard word spent once, and how many there are.
     *
     * @return array{found: int, total: int}
     */
    public function tally(string $heard, string $target, LanguagePack $pack): array
    {
        $speech = $pack->speech();
        $all = $this->speech->words($target, $speech);
        $drop = [...$speech->unstressed, ...$speech->articles];
        $wanted = self::without($all, $drop);
        // A target of nothing but unstressed words is asked for whole — and then nothing is dropped from what was heard.
        $spare = $wanted === [] ? [] : $drop;
        $wanted = $wanted === [] ? $all : $wanted;
        if ($wanted === []) {
            return ['found' => 0, 'total' => 0];
        }
        $heardWords = self::without($this->boundary->align($this->speech->words($heard, $speech), $all), $spare);

        return ['found' => $this->matches($wanted, $heardWords, $pack), 'total' => count($wanted)];
    }

    /**
     * HOW MUCH OF A LINE THE MOVE HAS ALREADY SAID (наряд BACK-TAILS-2 §9) — rule (в) read with the role's line as the
     * target: the share of the line's key words the heard move holds, by their bases, in any order, each heard word spent
     * once. 1 when every key word of the line was in the move — «It started three days ago.» after «His lower back hurts,
     * and three days ago it started.» — however much more the move said. With `$swapPersons` the move is read with its
     * first and second person swapped (the pack's `person_swap`): «My son has a fever» heard as «your son has a fever».
     */
    public function share(string $line, string $heard, LanguagePack $pack, bool $swapPersons = false): float
    {
        if ($swapPersons && $pack->has('person_swap')) {
            $swap = $pack->map('person_swap');
            $heard = implode(' ', array_map(
                static fn (string $w): string => is_string($swap[$w] ?? null) ? $swap[$w] : $w,
                $this->speech->words($heard, $pack->speech()),
            ));
        }
        ['found' => $found, 'total' => $total] = $this->tally($heard, $line, $pack);

        return $total === 0 ? 0.0 : $found / $total;
    }

    /**
     * Rule (б): the bases a word may be a form of — the word itself, its irregular base, and what every regular ending
     * the pack knows leaves of it (each with its irregular base too). A language without `irregular_forms` has only the
     * word itself.
     *
     * @return list<string>
     */
    public function bases(string $word, LanguagePack $pack): array
    {
        if (! $pack->has('irregular_forms')) {
            return [$word];
        }
        $irregular = $pack->map('irregular_forms');
        $out = [$word];
        foreach ($pack->has('inflection_rules') ? $pack->map('inflection_rules') : [] as $rule) {
            if (! is_array($rule) || ! is_string($rule[0] ?? null) || ! is_string($rule[1] ?? null)) {
                continue;
            }
            $base = preg_replace($rule[0], $rule[1], $word, 1, $count);
            if ($count > 0 && is_string($base) && $base !== '') {
                $out[] = $base;
            }
        }
        foreach ($out as $form) {
            $base = $irregular[$form] ?? null;
            if (is_string($base) && $base !== '') {
                $out[] = $base;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * How many of `$wanted` are among `$available` by their bases, each available word spent once.
     *
     * @param  list<string>  $wanted
     * @param  list<string>  $available
     */
    private function matches(array $wanted, array $available, LanguagePack $pack): int
    {
        $pool = array_map(fn (string $w): array => $this->bases($w, $pack), $available);
        $found = 0;
        foreach ($wanted as $word) {
            $mine = $this->bases($word, $pack);
            foreach ($pool as $i => $theirs) {
                if (array_intersect($mine, $theirs) !== []) {
                    unset($pool[$i]);
                    $found++;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $drop
     * @return list<string>
     */
    private static function without(array $words, array $drop): array
    {
        if ($drop === []) {
            return $words;
        }
        $index = array_fill_keys($drop, true);

        return array_values(array_filter($words, static fn (string $w): bool => ! isset($index[$w])));
    }
}
