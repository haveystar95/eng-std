<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\Service\SpokenWordBoundary;

/**
 * DID THE LEARNER SAY THE CONSTRUCTION — WITH A VALUE OF THEIR OWN (наряд FIX-3 §6; «как человек» — BACK-TAILS-2 §2).
 *
 * A target of the talk is a frame with a window ({@see ConversationPhrase}), and the learner came to say IT, not the
 * lesson's sentence: «I don't have any experience» says «I have ___ of experience», «Yes it is my first visit» says
 * «This is ___». The rule:
 *
 * (а) Both sides in one form ({@see SpeechMatch::words()}: case, marks, contractions spelt out, numbers in digits).
 * (б) The KEY WORDS are the frame's own words outside its window that carry something — the pack's `unstressed_words`
 *     left out; a frame with fewer than {@see MIN_KEY} such words keeps all of its words. A key word is found by its
 *     BASE ({@see bases()}: the pack's endings and irregular forms), in any order, each heard word spent once; from
 *     {@see LENIENT_FROM} key words on, one may be missing.
 * (в) THE WINDOW IS FILLED — something of the learner's own was said where the value goes ({@see valueOf()}): the words
 *     between the frame's part before the window and its part after it, or, when the move put them elsewhere
 *     («Weekdays works for me» for «That works for me on ___»), the words the frame's own do not account for — at least
 *     one of them a word that carries something. The value may be the lesson's, another of the window's, or anything
 *     else: what goes into the window is the learner's. A frame without a window has nothing to fill.
 * (г) SAID when (в) holds and either every key word is heard (б), or — THE MODEL'S WORD, the second support and never
 *     the first — the role named the target in its `phrases_used` and at least {@see VOUCHED} of its key words are
 *     heard: «Yes it is my first visit» is «This is ___» by the role's ear and half its key words.
 *     A QUESTION is asked with its opening word: a frame that asks («Should I tell you ___?», «Where is ___?») is said
 *     only when the move holds its first word too, whatever else it holds — the question word or the auxiliary is often
 *     `unstressed` and so no key word, and recognition gives no «?». «I told you. A cough.» holds every key word of
 *     «Should I tell you ___?» (the rehearsal of the FIX-3 live run) and asks nothing.
 * (д) One target, once: a move is read only for the targets not said yet, and for all of them — not only for the
 *     scene the move stands in.
 *
 * This is the TALK's rule. The cards keep theirs — {@see SpeechMatch} and the slot judge — and nothing here reaches
 * them; {@see share()} is the same reading of words the role's echo guard measures by ({@see RoleLines}).
 */
final readonly class PhraseUse
{
    /** From this many key words on, one of them may go missing and the construction is still said. */
    public const LENIENT_FROM = 4;

    /** The share of a target's key words the heard line must hold for the model's word to count (rule г). */
    public const VOUCHED = 0.5;

    /** Fewer key words than this — the frame's every word is its key (rule б). */
    public const MIN_KEY = 2;

    /** 1 of 2 is exactly a half, and a float says otherwise. */
    private const EPSILON = 1e-9;

    public function __construct(
        private SpeechMatch $speech = new SpeechMatch,
        private SpokenWordBoundary $boundary = new SpokenWordBoundary,
    ) {}

    /**
     * The targets a move says, of those not said yet (rules г, д).
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
        foreach ($unsaid as $target) {
            if ($target->hasWindow() && $this->valueOf($heard, $target, $pack) === null) {
                continue;
            }
            if (! $this->opensAsItAsks($heard, $target, $pack)) {
                continue;
            }
            ['found' => $found, 'total' => $total] = $this->keyTally($heard, $target, $pack);
            $all = $total > 0 && $found >= $total - ($total >= self::LENIENT_FROM ? 1 : 0);
            $vouched = isset($named[$target->id()]) && $total > 0 && $found / $total + self::EPSILON >= self::VOUCHED;
            if ($all || $vouched) {
                $out[] = $target->id();
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * THE LEARNER'S VALUE IN THE WINDOW (rule в) as it was said — «general fitness», «any», «my first visit» — or null
     * when nothing of the learner's own was said there, and for a frame without a window.
     */
    public function valueOf(string $heard, ConversationPhrase $target, LanguagePack $pack): ?string
    {
        $parts = preg_split(FrameText::SLOT_PATTERN, FrameText::withoutEndMark($target->frameTarget), 2);
        if (! is_array($parts) || count($parts) < 2 || trim($heard) === '') {
            return null;
        }
        $speech = $pack->speech();
        $before = $this->speech->words($parts[0], $speech);
        $after = $this->speech->words($parts[1], $speech);
        // The move word by word, each word knowing the sentence it stands in: a value ends where its sentence does.
        $units = [];
        $sentenceOf = [];
        foreach (preg_split('/(?<=[.?!…])\s+/u', trim($heard), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $n => $sentence) {
            foreach ($this->speech->surfaceWords($sentence, $speech) as $unit) {
                $units[] = $unit;
                $sentenceOf[] = $n;
            }
        }
        /** @var list<array{token: string, unit: int}> $tokens */
        $tokens = [];
        foreach ($units as $unit => [, $words]) {
            foreach ($words as $word) {
                $tokens[] = ['token' => $word, 'unit' => $unit];
            }
        }

        // Between the frame's part before the window and its part after it, as the move said them.
        $start = 0;
        foreach ($before as $word) {
            $at = $this->find($word, $tokens, $start, $pack);
            if ($at !== null) {
                $start = $at + 1;
            }
        }
        $end = count($tokens);
        // «ok. he's been sick for two days. should I tell you about his sleep» — the window of «He's been sick ___» is
        // «for two days», not the rest of the move (the rehearsal of the FIX-3 live run).
        if ($start > 0 && $start < $end) {
            $in = $sentenceOf[$tokens[$start - 1]['unit']];
            for ($i = $start; $i < $end; $i++) {
                if ($sentenceOf[$tokens[$i]['unit']] !== $in) {
                    $end = $i;
                    break;
                }
            }
        }
        foreach ($after as $word) {
            $at = $this->find($word, $tokens, $start, $pack);
            if ($at !== null && $at < $end) {
                $end = $at;
            }
        }
        $span = range($start, $end - 1);
        if ($start >= $end || ! $this->carries($span, $tokens, $pack)) {
            // Elsewhere in the move: the words the frame's own do not account for — in the sentence the frame's words
            // stand in. «here is my passport. I'm flying to» leaves the window of «I'm flying to ___» empty; the passport
            // of the sentence before is not its value (the last check-in of the FIX-3 live run).
            $anchor = $start > 0 ? $sentenceOf[$tokens[$start - 1]['unit']] : null;
            $within = $anchor === null ? $tokens : array_filter($tokens, static fn (array $t): bool => $sentenceOf[$t['unit']] === $anchor);
            $span = $this->leftover([...$before, ...$after], $within, $pack);
            if (! $this->carries($span, $tokens, $pack)) {
                return null;
            }
        }
        $chosen = [];
        foreach ($span as $i) {
            $chosen[$tokens[$i]['unit']] = true;
        }

        return implode(' ', array_map(static fn (int $unit): string => $units[$unit][0], array_keys($chosen)));
    }

    /**
     * DOES THE MOVE BREAK OFF (наряд FIX-3 §7: «обрывок ≠ „не понял"»): it stops on a word no sentence ends on — the pack's
     * `dangling_words`: «Yes my», «I have a» — or right where the window of one of the talk's constructions opens: «I'm
     * flying to» for «I'm flying to ___.» (the check-in of the live run, judged «not understood» by the role). Such a move
     * was not misunderstood; it was not finished.
     *
     * @param  list<ConversationPhrase>  $targets  the talk's targets
     */
    public function breaksOff(string $heard, array $targets, LanguagePack $pack): bool
    {
        $speech = $pack->speech();
        $words = $this->speech->words($heard, $speech);
        if ($words === []) {
            return false;
        }
        if ($pack->has('dangling_words') && in_array($words[count($words) - 1], $pack->words('dangling_words'), true)) {
            return true;
        }
        foreach ($targets as $target) {
            $parts = preg_split(FrameText::SLOT_PATTERN, FrameText::withoutEndMark($target->frameTarget), 2);
            $tail = is_array($parts) && count($parts) === 2 ? array_slice($this->speech->words($parts[0], $speech), -2) : [];
            if ($tail !== [] && array_slice($words, -count($tail)) === $tail) {
                return true;
            }
        }

        return false;
    }

    /** Does the move hold the opening word of a frame that asks (rule г)? A frame that does not ask — nothing to hold. */
    private function opensAsItAsks(string $heard, ConversationPhrase $target, LanguagePack $pack): bool
    {
        if (! str_ends_with(rtrim($target->frameTarget), '?')) {
            return true;
        }
        $speech = $pack->speech();
        $first = $this->speech->words(FrameParts::part($target->frameTarget), $speech)[0] ?? null;

        return $first === null || $this->matches([$first], $this->speech->words($heard, $speech), $pack) === 1;
    }

    /**
     * How many of the target's key words (rule б) the heard line holds, each heard word spent once, and how many there are.
     *
     * @return array{found: int, total: int}
     */
    public function keyTally(string $heard, ConversationPhrase $target, LanguagePack $pack): array
    {
        $speech = $pack->speech();
        $all = $this->speech->words(FrameParts::part($target->frameTarget), $speech);
        $key = self::without($all, $speech->unstressed);
        $key = count($key) < self::MIN_KEY ? $all : $key;
        if ($key === []) {
            return ['found' => 0, 'total' => 0];
        }

        return ['found' => $this->matches($key, $this->boundary->align($this->speech->words($heard, $speech), $all), $pack), 'total' => count($key)];
    }

    /**
     * HOW MUCH OF A LINE A MOVE HAS ALREADY SAID (наряд BACK-TAILS-2 §9) — the share of the line's key words (the pack's
     * `unstressed_words` and `articles` aside; a line of nothing but those is read whole) the heard move holds, by their
     * bases, in any order, each heard word spent once. 1 when every key word of the line was in the move — «It started
     * three days ago.» after «His lower back hurts, and three days ago it started.» — however much more the move said.
     * With `$swapPersons` the move is read with its first and second person swapped (the pack's `person_swap`): «My son
     * has a fever» heard as «your son has a fever».
     */
    public function share(string $line, string $heard, LanguagePack $pack, bool $swapPersons = false): float
    {
        $speech = $pack->speech();
        if ($swapPersons && $pack->has('person_swap')) {
            $swap = $pack->map('person_swap');
            $heard = implode(' ', array_map(
                static fn (string $w): string => is_string($swap[$w] ?? null) ? $swap[$w] : $w,
                $this->speech->words($heard, $speech),
            ));
        }
        $all = $this->speech->words($line, $speech);
        $drop = [...$speech->unstressed, ...$speech->articles];
        $wanted = self::without($all, $drop);
        // A line of nothing but unstressed words is read whole — and then nothing is dropped from what was heard.
        $spare = $wanted === [] ? [] : $drop;
        $wanted = $wanted === [] ? $all : $wanted;
        if ($wanted === []) {
            return 0.0;
        }
        $heardWords = self::without($this->boundary->align($this->speech->words($heard, $speech), $all), $spare);

        return $this->matches($wanted, $heardWords, $pack) / count($wanted);
    }

    /**
     * The bases a word may be a form of — the word itself, its irregular base, and what every regular ending the pack
     * knows leaves of it (each with its irregular base too). A language without `irregular_forms` has only the word itself.
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
     * Where `$word` is heard at `$from` or after, by its bases — the first place, or null.
     *
     * @param  list<array{token: string, unit: int}>  $tokens
     */
    private function find(string $word, array $tokens, int $from, LanguagePack $pack): ?int
    {
        $mine = $this->bases($word, $pack);
        for ($i = $from, $n = count($tokens); $i < $n; $i++) {
            if (array_intersect($mine, $this->bases($tokens[$i]['token'], $pack)) !== []) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The heard words the frame's own words do not account for, by their bases, each frame word spending one heard word.
     *
     * @param  list<string>  $frameWords
     * @param  array<int, array{token: string, unit: int}>  $tokens  the move's words, or those of one sentence of it, by their place in the move
     * @return list<int>
     */
    private function leftover(array $frameWords, array $tokens, LanguagePack $pack): array
    {
        $free = array_keys($tokens);
        foreach ($frameWords as $word) {
            $mine = $this->bases($word, $pack);
            foreach ($free as $k => $i) {
                if (array_intersect($mine, $this->bases($tokens[$i]['token'], $pack)) !== []) {
                    unset($free[$k]);
                    break;
                }
            }
        }

        return array_values($free);
    }

    /**
     * Does a stretch of the move carry something — a word that is neither one of the pack's unstressed ones nor one no
     * sentence ends on? «Here is my» put «my» in the window of «Here is ___» and was credited (the last check-in of the
     * FIX-3 live run): a word that waits for its noun fills nothing.
     *
     * @param  list<int>  $span
     * @param  list<array{token: string, unit: int}>  $tokens
     */
    private function carries(array $span, array $tokens, LanguagePack $pack): bool
    {
        $empty = array_fill_keys([...$pack->speech()->unstressed, ...($pack->has('dangling_words') ? $pack->words('dangling_words') : [])], true);
        foreach ($span as $i) {
            if (! isset($empty[$tokens[$i]['token']])) {
                return true;
            }
        }

        return false;
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
