<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;

/**
 * DID THE LEARNER SAY THE CONSTRUCTION — AS A COHERENT PHRASE, NOT AS A BAG OF WORDS (наряд FIX-4 §2).
 *
 * The key words of a frame counted anywhere in a move credited what nobody said: «Hello what kind of memberships do you
 * have» was «What ___ do you have?» AND «Do you have ___?» (do, you, have stood in the other phrase), and «Nice work
 * days work for me» was «That works for me on ___» and a frame of the next scene too — while «Yes I have a shoulder pain»
 * was not «I have some ___», and the learner spent four moves guessing what was wanted. The rule now:
 *
 * - Both sides in one form ({@see FrameWords}): case and marks gone, contractions spelt out, articles out of it.
 * - A frame is its part before the window, the window, and its part after it (possibly nothing). It is SAID when a
 *   sentence of the move holds the part before as an unbroken run of words, then a window of at least one word, then the
 *   part after straight after it — and the part before stands where the move begins, or straight after the words the
 *   pack says a move may open with (`intro_words`: «hello», «yes», «ok», «thank you»…). A frame without a window is said
 *   when its words stand so. So «what kind of memberships do you have» says «What ___ do you have?» and not «Do you have
 *   ___?»: that one's part before stands after «memberships».
 * - A CONJUNCTION BEGINS A CLAUSE (наряд FIX-4b §1): learners glue their constructions into one sentence, and speech
 *   recognition sends it without a full stop — so a frame may also start straight after a word the pack says a clause
 *   opens with (`clause_starters`: «and», «but», «so», «then», «or»; a comma before it or not), wherever it stands:
 *   «Yes, this is my first visit and I have about a year of experience» says «This is ___» and «I have ___ of
 *   experience», «I have a fever and I have some shoulder pain» says «I have some ___». Nowhere else in the middle of a
 *   sentence: there is no conjunction before «do» in «what kind of memberships do you have». A window with nothing of its
 *   frame after it, which runs to the end of its sentence, ends before the conjunction another construction of the move
 *   is said after — «my first visit», not the clause of the next construction ({@see self::windowOf()}).
 * - It is said ALMOST when the same holds with ONE difference of words in its parts before and after the window — a
 *   word replaced, added or left out, another form of a word counted as another word («works»/«work»): «I have a
 *   shoulder pain» is «I have some ___» almost, «Yes it is my first visit» is «This is ___» almost. Two differences are
 *   nothing. An almost made of the very words that say another frame of the move is no almost at all: the learner was
 *   saying that one («I have about one year of experience» is no «I have some ___»).
 * - A NEGATIVE IS THE SAME CONSTRUCTION (канон владельца, DECISIONS п. 395): inside the frame's words, the pack's
 *   `negation` is no difference — «not» after a form of be, a modal or the auxiliary have, and «do/does/did not» before a
 *   verb, which is then read by its base: «I don't have any experience» says «I have ___ of experience», «he doesn't
 *   have a fever» says «He has ___». The window is free and the negation in it is the learner's value.
 * - IN EVERY LANGUAGE ITS OWN WAY (наряд LANG-1 §1): the pack's `negation` is a list of `words`, each free right after a
 *   word of `after` when `after` is a list (English: be, the modals, have) and ANYWHERE — the first word of the move too
 *   — when `after` is null or not written: pl «Nie mam gorączki» says «Mam ___», ro «Nu am febră» says «Am ___», es «No
 *   tengo fiebre» «Tengo ___», it «Non ho la febbre» «Ho ___», fr «Je n'ai pas de fièvre» «J'ai ___» (ne and pas both
 *   free, the elision spelt out by {@see FrameWords}), de «Ich habe keine Zeit am Montag» «Ich habe Zeit am ___».
 *   `do_support` stays the English rule. A negation word is only ever free when it is ADDED to the frame's words — one the
 *   frame has and the move leaves out is a difference like any other, so a positive move is at most almost a negative
 *   frame. The old English shape with one `word` is read as `words: [word]`.
 * - THE PARTITIVE GOES WITH A QUANTITY (the pack's `partitive`): a window of one determiner («no», «any», «some»…) may
 *   leave out the frame's «of» after it — «I have no experience» is «I have ___ of experience».
 *
 * Deterministic and pure (the project's rule: no judge on a model in the hot path). Which frames it is asked about — the
 * current scene's, the ones not said yet — is the caller's.
 */
final readonly class FrameJudge
{
    /** How many differences of words a construction said almost may have. */
    public const ALMOST = 1;

    /** The cost of an alignment that cannot be. */
    private const NEVER = PHP_INT_MAX;

    /**
     * WHAT A MOVE SAYS of the frames it is judged against: those it says, those it says almost, and what went into the
     * window of each one said.
     *
     * @param  list<ConversationPhrase>  $frames
     */
    public function move(string $heard, array $frames, LanguagePack $pack): MoveVerdict
    {
        $sentences = FrameWords::sentences($heard, $pack);
        $negation = self::negation($pack);
        $matches = [];
        foreach ($frames as $frame) {
            $match = $this->match($sentences, $frame, $pack, $negation);
            if ($match !== null) {
                $matches[$frame->id()] = $match;
            }
        }
        $saying = [];
        foreach ($matches as $match) {
            if ($match['cost'] === 0) {
                $saying[$match['sentence'].':'.$match['start']] = true;
            }
        }
        $said = [];
        $almost = [];
        $values = [];
        foreach ($matches as $id => $match) {
            if ($match['cost'] === 0) {
                $said[] = $id;
                $sentence = $sentences[$match['sentence']];
                $values[$id] = $match['window'] === null ? null : self::value($sentence, ...self::windowOf($id, $matches, $sentence, $pack));
            } elseif (! isset($saying[$match['sentence'].':'.$match['start']])) {
                $almost[] = $id;
            }
        }

        return new MoveVerdict($said, $almost, $values);
    }

    /**
     * THE WINDOW OF A CONSTRUCTION SAID, AS FAR AS IT GOES (наряд FIX-4b §1). A window with nothing of its frame after it
     * runs to the end of its sentence — unless another construction the move says starts inside it straight after a
     * conjunction: then it ends before that conjunction, and what follows is the other construction's. «this is my first
     * visit and I have about a year of experience» puts «my first visit» in the window of «This is ___»; «He has a fever
     * and a sore throat», with no construction after «and», keeps «a fever and a sore throat». What is left must still be
     * the learner's own, else the window runs on as it was. Only what went into the window is read here — whether the
     * construction is said is not.
     *
     * @param  array<string, array{cost: int, sentence: int, start: int, window: array{0: int, 1: int}|null, open: bool, frameWords: list<string>}>  $matches
     * @param  array{words: list<string>, at: list<int>, written: list<string>}  $sentence
     * @return array{0: int, 1: int}
     */
    private static function windowOf(string $id, array $matches, array $sentence, LanguagePack $pack): array
    {
        $match = $matches[$id];
        [$from, $to] = $match['window'] ?? [0, 0];
        if (! $match['open']) {
            return [$from, $to];
        }
        $clauses = self::clauseStarts($sentence['words'], $pack);
        foreach ($matches as $other => $there) {
            $conjunction = $clauses[$there['start']] ?? null;
            if ($other === $id || $there['cost'] !== 0 || $there['sentence'] !== $match['sentence'] || $conjunction === null) {
                continue;
            }
            if ($conjunction > $from && $conjunction < $to && array_diff(array_slice($sentence['words'], $from, $conjunction - $from), $match['frameWords']) !== []) {
                $to = $conjunction;
            }
        }

        return [$from, $to];
    }

    /**
     * DOES THE MOVE BREAK OFF (наряд FIX-3 §7: «обрывок ≠ „не понял"»): it stops on a word no sentence ends on — the pack's
     * `dangling_words`: «Yes my», «I have a» — or right where the window of one of the frames opens: «I'm flying to» for
     * «I'm flying to ___.». Such a move was not misunderstood; it was not finished.
     *
     * @param  list<ConversationPhrase>  $frames
     */
    public function breaksOff(string $heard, array $frames, LanguagePack $pack): bool
    {
        $sentences = FrameWords::sentences($heard, $pack, articles: true);
        $words = $sentences === [] ? [] : $sentences[count($sentences) - 1]['words'];
        if ($words === []) {
            return false;
        }
        if (in_array([$words[count($words) - 1]], self::entries('dangling_words', $pack), true)) {
            return true;
        }
        foreach ($frames as $frame) {
            $parts = preg_split(FrameText::SLOT_PATTERN, FrameText::withoutEndMark($frame->frameTarget), 2);
            $tail = is_array($parts) && count($parts) === 2 ? array_slice(FrameWords::of($parts[0], $pack, articles: true), -2) : [];
            if ($tail !== [] && array_slice($words, -count($tail)) === $tail) {
                return true;
            }
        }

        return false;
    }

    /**
     * The best reading of one frame in the move — the fewest differences, then the earliest sentence, start, window: where
     * it starts, the words of its window (null for a frame without one), whether that window runs to the end of the
     * sentence (nothing of the frame after it) and the frame's own words.
     *
     * @param  list<array{words: list<string>, at: list<int>, written: list<string>}>  $sentences
     * @param  array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}  $negation
     * @return array{cost: int, sentence: int, start: int, window: array{0: int, 1: int}|null, open: bool, frameWords: list<string>}|null
     */
    private function match(array $sentences, ConversationPhrase $frame, LanguagePack $pack, array $negation): ?array
    {
        $parts = preg_split(FrameText::SLOT_PATTERN, FrameText::withoutEndMark($frame->frameTarget), 2);
        $window = is_array($parts) && count($parts) === 2;
        $before = FrameWords::of($window ? $parts[0] : $frame->frameTarget, $pack);
        $after = $window ? FrameWords::of($parts[1], $pack) : [];

        $best = null;
        foreach ($sentences as $n => $sentence) {
            foreach (self::starts($sentence['words'], $pack) as $start) {
                $found = $window
                    ? $this->withWindow($before, $after, $sentence, $start, $pack, $negation)
                    : $this->whole($before, $sentence['words'], $start, $pack, $negation);
                if ($found !== null && ($best === null || $found['cost'] < $best['cost'])) {
                    $best = [
                        'cost' => $found['cost'], 'sentence' => $n, 'start' => $start, 'window' => $found['window'],
                        'open' => $window && $after === [], 'frameWords' => [...$before, ...$after],
                    ];
                }
                if ($best !== null && $best['cost'] === 0) {
                    return $best;
                }
            }
        }

        return $best !== null && $best['cost'] <= self::ALMOST ? $best : null;
    }

    /**
     * A frame without a window: its words from `$start` on, followed by anything.
     *
     * @param  list<string>  $frame
     * @param  list<string>  $words
     * @param  array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}  $negation
     * @return array{cost: int, window: null}|null
     */
    private function whole(array $frame, array $words, int $start, LanguagePack $pack, array $negation): ?array
    {
        if ($frame === []) {
            return null;
        }
        $cost = min([self::NEVER, ...$this->costs($frame, $words, $start, $pack, $negation)]);

        return $cost <= self::ALMOST ? ['cost' => $cost, 'window' => null] : null;
    }

    /**
     * A frame with a window: its part before from `$start`, a window of one word or more, its part after straight after
     * the window (the rest of the sentence may follow it); with nothing after the window, the window runs to the end of the
     * sentence.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @param  array{words: list<string>, at: list<int>, written: list<string>}  $sentence
     * @param  array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}  $negation
     * @return array{cost: int, window: array{0: int, 1: int}}|null
     */
    private function withWindow(array $before, array $after, array $sentence, int $start, LanguagePack $pack, array $negation): ?array
    {
        $words = $sentence['words'];
        $n = count($words);
        $own = array_fill_keys([...$before, ...$after], true);
        $best = null;
        /** @var array<string, int> $afterCosts the cheapest reading of a part after the window from a place — by place and part */
        $afterCosts = [];
        foreach ($this->costs($before, $words, $start, $pack, $negation) as $j => $costBefore) {
            $from = $start + $j;
            if ($costBefore > self::ALMOST || $from >= $n) {
                continue;
            }
            $ends = $after === [] ? [$n] : range($from + 1, $n);
            foreach ($ends as $to) {
                // The window carries something of the learner's own: «I'm working on» is no «I'm working on ___» with
                // «on» in its window and one word of the frame left out — it broke off where the window opens.
                if (array_diff(array_slice($words, $from, $to - $from), array_keys($own)) === []) {
                    continue;
                }
                $costAfter = $after === [] ? 0 : self::NEVER;
                foreach ($after === [] ? [] : self::afters($after, $words, $from, $to, $pack) as $shortened => $part) {
                    $key = $to.':'.count($part);
                    $afterCosts[$key] ??= min([self::NEVER, ...$this->costs($part, $words, $to, $pack, $negation)]);
                    // Without its partitive word the part after forgives that word and nothing more: said, or not at all.
                    if ($shortened === 0 || $costBefore + $afterCosts[$key] === 0) {
                        $costAfter = min($costAfter, $afterCosts[$key]);
                    }
                }
                if ($costAfter === self::NEVER) {
                    continue;
                }
                $cost = $costBefore + $costAfter;
                if ($best === null || $cost < $best['cost']) {
                    $best = ['cost' => $cost, 'window' => [$from, $to]];
                }
            }
        }

        return $best !== null && $best['cost'] <= self::ALMOST ? $best : null;
    }

    /**
     * The frame's part after its window as it may be said after this window: as it is (key 0), and — after a window of
     * one determiner — without the partitive word it starts with (key 1: «I have no experience»).
     *
     * @param  list<string>  $after
     * @param  list<string>  $words
     * @return array<int, list<string>>
     */
    private static function afters(array $after, array $words, int $from, int $to, LanguagePack $pack): array
    {
        $partitive = $pack->has('partitive') ? $pack->map('partitive') : [];
        $word = is_string($partitive['word'] ?? null) ? $partitive['word'] : null;
        $determiners = is_array($partitive['determiners'] ?? null) ? $partitive['determiners'] : [];
        if ($word !== null && $after[0] === $word && $to - $from === 1 && in_array($words[$from], $determiners, true)) {
            return [0 => $after, 1 => array_slice($after, 1)];
        }

        return [0 => $after];
    }

    /**
     * THE DIFFERENCES between `$frame` and the move's words from `$from` to each place after it — `result[j]` for
     * `$words[$from .. $from + j)`: a word replaced, added or left out costs one; the negation of the pack costs nothing
     * (en: `not` after a form of be, a modal or have, `do/does/did not` before a verb read by its base; pl «nie», fr «ne»
     * and «pas» wherever they stand — {@see self::negation()}).
     *
     * @param  list<string>  $frame
     * @param  list<string>  $words
     * @param  array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}  $negation
     * @return list<int>
     */
    private function costs(array $frame, array $words, int $from, LanguagePack $pack, array $negation): array
    {
        $m = count($frame);
        $len = count($words) - $from;
        $cost = array_fill(0, $m + 1, array_fill(0, $len + 1, self::NEVER));
        $cost[0][0] = 0;
        $relax = static function (array &$cost, int $i, int $j, int $value): void {
            if ($value < $cost[$i][$j]) {
                $cost[$i][$j] = $value;
            }
        };
        for ($i = 0; $i <= $m; $i++) {
            for ($j = 0; $j <= $len; $j++) {
                $here = $cost[$i][$j];
                if ($here === self::NEVER) {
                    continue;
                }
                $at = $from + $j;
                if ($i < $m && $j < $len) {
                    $relax($cost, $i + 1, $j + 1, $here + ($frame[$i] === $words[$at] ? 0 : 1));
                    if ($j + 2 < $len && self::doSupport($words, $at, $frame[$i], $pack, $negation)) {
                        $relax($cost, $i + 1, $j + 3, $here);
                    }
                }
                if ($j < $len) {
                    $relax($cost, $i, $j + 1, $here + (self::freeNot($words, $at, $negation) ? 0 : 1));
                }
                if ($i < $m) {
                    $relax($cost, $i + 1, $j, $here + 1);
                }
            }
        }

        return $cost[$m];
    }

    /**
     * Is `$words[$at]` a negation word the pack forgives here — right after a word of its `after` list («is not», «can
     * not»), or anywhere when the pack names no such list («nie mam», «je ne ai pas»)?
     *
     * @param  list<string>  $words
     * @param  array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}  $negation
     */
    private static function freeNot(array $words, int $at, array $negation): bool
    {
        if (! isset($negation['words'][$words[$at]])) {
            return false;
        }

        return $negation['after'] === null || ($at > 0 && isset($negation['after'][$words[$at - 1]]));
    }

    /**
     * Do `$words[$at .. $at + 3)` say «do/does/did not» + the frame's verb in any form — «doesn't have» for «has»?
     *
     * @param  list<string>  $words
     * @param  array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}  $negation
     */
    private static function doSupport(array $words, int $at, string $verb, LanguagePack $pack, array $negation): bool
    {
        return isset($negation['do_support'][$words[$at]])
            && isset($negation['words'][$words[$at + 1]])
            && WordBases::meet($words[$at + 2], $verb, $pack);
    }

    /**
     * THE PACK'S NEGATION AS THE MOVE'S WORDS ARE READ (наряд LANG-1 §1) — `words`, the words each may follow (`after`: a
     * list, or null for anywhere) and the English `do_support`, each word read by {@see FrameWords} as the move is, so a
     * pack that writes «N'» or «Nicht» still meets «ne» and «nicht». The old shape — one `word` — is `words: [word]`; a
     * pack that writes no `negation`, or an empty one, forgives nothing. An entry that reads as more than one word can
     * never stand for one word of the move, and is left out.
     *
     * @return array{words: array<string, true>, after: array<string, true>|null, do_support: array<string, true>}
     */
    private static function negation(LanguagePack $pack): array
    {
        $negation = $pack->has('negation') ? $pack->map('negation') : [];
        $words = $negation['words'] ?? (isset($negation['word']) ? [$negation['word']] : []);
        $after = $negation['after'] ?? null;

        return [
            'words' => self::oneWordEach(is_array($words) ? $words : [], $pack),
            'after' => is_array($after) ? self::oneWordEach($after, $pack) : null,
            'do_support' => self::oneWordEach(is_array($negation['do_support'] ?? null) ? $negation['do_support'] : [], $pack),
        ];
    }

    /**
     * The entries of a list of the pack that read as exactly one word of a move, as a set.
     *
     * @param  array<mixed>  $entries
     * @return array<string, true>
     */
    private static function oneWordEach(array $entries, LanguagePack $pack): array
    {
        $out = [];
        foreach ($entries as $entry) {
            $said = is_string($entry) ? FrameWords::of($entry, $pack, articles: true) : [];
            if (count($said) === 1) {
                $out[$said[0]] = true;
            }
        }

        return $out;
    }

    /**
     * Where a frame may start in a sentence: at its first word, after each run of the pack's opening words from there
     * («ok», «thank you» — «OK thank you how do I use this machine» starts «How do I use ___?» at its fourth word), and
     * straight after each word of the pack a clause opens with, wherever it stands (наряд FIX-4b §1: «… my first visit
     * and I have about a year of experience» starts «I have ___ of experience» after «and»). In order, each once.
     *
     * @param  list<string>  $words
     * @return list<int>
     */
    private static function starts(array $words, LanguagePack $pack): array
    {
        $openers = self::entries('intro_words', $pack);
        $out = [0];
        $at = 0;
        while (true) {
            $step = 0;
            foreach ($openers as $opener) {
                if (array_slice($words, $at, count($opener)) === $opener) {
                    $step = max($step, count($opener));
                }
            }
            if ($step === 0) {
                break;
            }
            $at += $step;
            $out[] = $at;
        }
        $out = array_filter(array_unique([...$out, ...array_keys(self::clauseStarts($words, $pack))]), static fn (int $start): bool => $start < count($words));
        sort($out);

        return $out;
    }

    /**
     * WHERE A CLAUSE OF THE SENTENCE BEGINS (наряд FIX-4b §1): the place straight after each of the pack's `clause_starters`
     * («and», «but», «so», «then», «or»), with the place of that word — where a window before it may end.
     *
     * @param  list<string>  $words
     * @return array<int, int> the place a clause starts at => the place of the word it starts after
     */
    private static function clauseStarts(array $words, LanguagePack $pack): array
    {
        $out = [];
        foreach (self::entries('clause_starters', $pack) as $starter) {
            for ($at = 0; $at + count($starter) <= count($words); $at++) {
                if (array_slice($words, $at, count($starter)) === $starter) {
                    $out[$at + count($starter)] = $at;
                }
            }
        }

        return $out;
    }

    /**
     * A list of the pack's words as the move's words are read — each entry its words («thank you» is two); an empty entry
     * or a key the pack does not write gives nothing.
     *
     * @return list<list<string>>
     */
    private static function entries(string $key, LanguagePack $pack): array
    {
        $out = [];
        foreach ($pack->has($key) ? $pack->words($key) : [] as $entry) {
            $said = FrameWords::of($entry, $pack, articles: true);
            if ($said !== []) {
                $out[] = $said;
            }
        }

        return $out;
    }

    /**
     * The window as the learner said it: the written words from the first of the window to the last, marks around them
     * trimmed — «about one year», «45 seconds», «Weekdays» — with the article that opens it («the general fitness»),
     * which the comparison left out and the learner said.
     *
     * @param  array{words: list<string>, at: list<int>, written: list<string>}  $sentence
     */
    private static function value(array $sentence, int $from, int $to): string
    {
        $first = $from > 0 ? $sentence['at'][$from - 1] + 1 : $sentence['at'][$from];
        $first = min($first, $sentence['at'][$from]);
        $last = $sentence['at'][$to - 1];

        return trim(implode(' ', array_filter(array_slice($sentence['written'], $first, $last - $first + 1), static fn (string $w): bool => $w !== '')));
    }
}
