<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE WORD RULES OF ONE LANGUAGE, READ OFF ITS PACK (наряд GEN-2b, `docs/plan-v2.md` §4) — the same questions for
 * every language, the answers from its pack: which words carry no content, when two words are forms of one, what
 * is a number or a time, whether a text asks, which words are too plain to teach, which past forms say the learner's
 * gender, which letters of a reading belong to another writing.
 *
 * Every method reads the keys it names from the pack and throws when one is missing: a rule asks first — a rule of
 * the day's checks asks its context, which hands it no reading of a language whose pack lacks a key it names
 * ({@see SkeletonContext::targetReading()}, `nativeReading()`, {@see DialogueContext::nativeReading()}) —, and a rule
 * whose language has no such key does not run, never answered with another language's words. Heuristic on purpose —
 * every code built on these is named a heuristic in the canon.
 */
final readonly class LanguageWords
{
    public function __construct(private LanguagePack $pack) {}

    // ── function words and content (`function_words`, `word_forms`) ─────────────────────────────────────────

    public function isFunction(string $token): bool
    {
        return $this->pack->listed('function_words', $token);
    }

    /**
     * The content words of a text — tokens that are no function word and long enough to carry content.
     *
     * @return list<string>
     */
    public function content(string $text): array
    {
        $min = $this->pack->mapInt('word_forms', 'content_min_letters');

        return array_values(array_filter(
            Words::tokens($text),
            fn (string $t): bool => ! $this->isFunction($t) && mb_strlen($t) >= $min,
        ));
    }

    /**
     * One word, or two forms of it: the shorter's letters but its last `stem_tail`, and never fewer than
     * `stem_min`, shared from the start.
     */
    public function sameStem(string $a, string $b): bool
    {
        $a = LanguagePack::normal($a);
        $b = LanguagePack::normal($b);
        if ($a === $b) {
            return true;
        }
        $least = $this->pack->mapInt('word_forms', 'stem_min');
        $tail = $this->pack->mapInt('word_forms', 'stem_tail');
        $min = min(mb_strlen($a), mb_strlen($b));
        if ($min < $least) {
            return false;
        }
        $prefix = 0;
        while ($prefix < $min && mb_substr($a, $prefix, 1) === mb_substr($b, $prefix, 1)) {
            $prefix++;
        }

        return $prefix >= max($least, $min - $tail);
    }

    /** How many content words of `$a` have a content word of the same stem in `$b`. */
    public function shared(string $a, string $b): int
    {
        $theirs = $this->content($b);
        $hits = 0;
        foreach (array_unique($this->content($a)) as $word) {
            foreach ($theirs as $other) {
                if ($this->sameStem($word, $other)) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits;
    }

    // ── numbers and time (`number_pattern`, `time_pattern`) ─────────────────────────────────────────────────

    public function isNumber(string $token): bool
    {
        return preg_match($this->pack->pattern('number_pattern'), LanguagePack::normal($token)) === 1;
    }

    public function isTime(string $token): bool
    {
        return preg_match($this->pack->pattern('time_pattern'), LanguagePack::normal($token)) === 1;
    }

    // ── sentences (`sentence_ends`, `abbreviations`) — the one rule of where a sentence ends is {@see SentenceEnds} ──

    /** Where a sentence of this language ends: the marks of the pack, an abbreviation's dot left out. */
    public function ends(): SentenceEnds
    {
        return new SentenceEnds($this->pack);
    }

    /** What the mark a text ends with says — `question`, `statement`… — or '' when it ends with none. */
    public function terminalKind(string $text): string
    {
        return $this->ends()->terminalKind($text);
    }

    /**
     * Does a text ask: it ends with a question mark, or — when the pack spells the word order of a question
     * (`question_word_order`) — its last sentence opens with an auxiliary and a subject pronoun, mark or no mark.
     */
    public function isQuestion(string $text): bool
    {
        $ends = $this->ends();
        if ($ends->terminalKind($text) === 'question') {
            return true;
        }
        if (! $this->pack->has('question_word_order')) {
            return false;
        }
        $sentences = $ends->sentences($text);
        $last = array_map(LanguagePack::normal(...), Words::tokens($sentences === [] ? '' : $sentences[count($sentences) - 1]));

        return count($last) >= 2
            && in_array($last[0], $this->pack->mapWords('question_word_order', 'auxiliaries'), true)
            && in_array($last[1], $this->pack->mapWords('question_word_order', 'subjects'), true);
    }

    // ── a closed list of the target (`everyday_words`) ──────────────────────────────────────────────────────

    public function isEveryday(string $token): bool
    {
        return $this->pack->listed('everyday_words', $token);
    }

    // ── the learner's own language (`script_letters`, `gendered_past_pattern`) ──────────────────────────────

    /**
     * The LETTERS of the reading that belong to another writing (`script_letters`, наряд BACK-TAILS-1 §3.2) — each one
     * once, in the order they stand. Only letters are looked at: a digit or a mark is no alphabet's, and the one thing
     * a learner cannot do is read an alphabet they do not know («ֆоутoуз» — an Armenian ֆ and two Latin o's among the
     * Cyrillic).
     *
     * A letter of NO writing of its own — Unicode's Common and Inherited scripts — is nobody's foreign letter (наряд
     * LANG-1): the Ukrainian and Belarusian apostrophe ʼ (U+02BC) is a modifier LETTER to Unicode but belongs to no
     * alphabet, and «пʼять», «інтэрвʼю» are spelled right; reading it as foreign made a correct reading FATAL.
     *
     * @return list<string>
     */
    public function foreignLetters(string $reading): array
    {
        $letters = $this->pack->pattern('script_letters');
        $out = [];
        foreach (preg_split('//u', $reading, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (preg_match('/^\p{L}$/u', $character) === 1
                && preg_match('/^[\p{Common}\p{Inherited}]$/u', $character) !== 1
                && preg_match($letters, $character) !== 1) {
                $out[$character] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * The gendered past forms a line says about the learner (`gendered_past_pattern`, its first group).
     *
     * @return list<string>
     */
    public function genderedPast(string $text): array
    {
        preg_match_all($this->pack->pattern('gendered_past_pattern'), mb_strtolower($text), $matches);

        return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    }
}
