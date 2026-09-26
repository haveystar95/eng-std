<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE WORD RULES OF ONE LANGUAGE, READ OFF ITS PACK (наряд GEN-2b, `docs/plan-v2.md` §4) — the same questions for
 * every language, the answers from its pack: which words carry no content, when two words are forms of one, what
 * is a number or a time, which mark ends a question, which article goes before which sound, when a filler is a
 * clause, which pronoun a frame leans on, which word of a native frame agrees with its slot, which letters a
 * reading may use.
 *
 * Every method reads the keys it names from the pack and throws when one is missing: a rule asks the context
 * first ({@see \App\Modules\Plan\Domain\Check\LessonValidationContext::reads()}), and a check whose language has
 * no such key is skipped and counted, never answered with another language's words. Heuristic on purpose — every
 * code built on these is named a heuristic in the canon.
 */
final readonly class LanguageWords
{
    /** A short answer that names nothing but numbers and time words («три дня», «со вчера»). */
    public const VALUE_NUMBER_OR_TIME = 'number_or_time';

    /** A count or a time of a thing («две воды», «14A у окна»). */
    public const VALUE_MIXED = 'mixed';

    /** No number and no time word at all («поясница»). */
    public const VALUE_OTHER = 'other';

    /** A subject and its verb open the text — a whole sentence («I am patient»). */
    public const SENTENCE = 'sentence';

    /** A subordinate clause, or a subject with its verb inside the text («if the fever returns»). */
    public const CLAUSE = 'clause';

    private const SLOT = '___';

    public function __construct(private LanguagePack $pack) {}

    public function language(): string
    {
        return $this->pack->code;
    }

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

    /**
     * The content words of `$a` that have no word of the same stem among the words of `$b`.
     *
     * @return list<string>
     */
    public function notIn(string $a, string $b): array
    {
        $theirs = Words::tokens($b);
        $out = [];
        foreach (array_unique($this->content($a)) as $word) {
            $found = false;
            foreach ($theirs as $other) {
                if ($this->sameStem($word, $other)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $out[] = $word;
            }
        }

        return $out;
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

    /** What kind of value a short answer names, as far as words tell without meaning (`function_words` too). */
    public function valueKind(string $text): string
    {
        $words = array_values(array_filter(Words::tokens($text), fn (string $t): bool => ! $this->isFunction($t)));
        $counted = count(array_filter($words, fn (string $w): bool => $this->isNumber($w) || $this->isTime($w)));

        return match (true) {
            $words !== [] && $counted === count($words) => self::VALUE_NUMBER_OR_TIME,
            $counted === 0 => self::VALUE_OTHER,
            default => self::VALUE_MIXED,
        };
    }

    // ── sentences (`sentence_ends`, `abbreviations`) — the one rule of where a sentence ends is {@see SentenceEnds} ──

    /** Where a sentence of this language ends: the marks of the pack, an abbreviation's dot left out. */
    public function ends(): SentenceEnds
    {
        return new SentenceEnds($this->pack);
    }

    /** The mark a text ends with — closing quotes and brackets aside — or '' when it ends with none. */
    public function terminal(string $text): string
    {
        return $this->ends()->terminal($text);
    }

    /** What the mark a text ends with says — `question`, `statement`… — or '' when it ends with none. */
    public function terminalKind(string $text): string
    {
        return $this->ends()->terminalKind($text);
    }

    /** Does a fragment (a filler) carry a sentence of its own — «See you tomorrow.», never «3 p.m.»? */
    public function carriesSentence(string $fragment): bool
    {
        return $this->ends()->carriesSentence($fragment);
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

    /** How many question marks a text has. */
    public function questionMarks(string $text): int
    {
        return $this->ends()->questionMarks($text);
    }

    /** How many sentences a text has, by the rule of where a sentence ends. */
    public function sentences(string $text): int
    {
        return $this->ends()->count($text);
    }

    /**
     * The names of a text: words written with a capital letter anywhere but at the start of a sentence
     * («Take Nurofen», «Dr. Smith» — the dot of «Dr.» ends no sentence), lower-cased (`function_words` too: «I» is
     * no name).
     *
     * @return list<string>
     */
    public function names(string $text): array
    {
        $out = [];
        foreach ($this->ends()->sentences($text) as $sentence) {
            foreach (array_slice(Words::surface($sentence), 1) as $word) {
                if (preg_match('/^\p{Lu}/u', $word) === 1 && ! $this->isFunction($word)) {
                    $out[] = mb_strtolower($word);
                }
            }
        }

        return array_values(array_unique($out));
    }

    // ── closed lists of the target (`everyday_words`, `ordinary_heads`, `closers`, `saying_verbs`, …) ───────

    public function isEveryday(string $token): bool
    {
        return $this->pack->listed('everyday_words', $token);
    }

    public function isOrdinaryHead(string $token): bool
    {
        return $this->pack->listed('ordinary_heads', $token);
    }

    public function isSaying(string $token): bool
    {
        return $this->pack->listed('saying_verbs', $token);
    }

    public function isAlternative(string $token): bool
    {
        return $this->pack->listed('alternative_words', $token);
    }

    /** A line that says nothing but «we are done» — its whole text, punctuation aside. */
    public function isCloser(string $line): bool
    {
        return $this->pack->listed('closers', implode(' ', array_map(LanguagePack::normal(...), Words::tokens($line))));
    }

    /** Two questions in one bubble: two question marks, or one sentence that asks again (`second_question_pattern`). */
    public function asksTwice(string $text): bool
    {
        return $this->questionMarks($text) >= 2 || preg_match($this->pack->pattern('second_question_pattern'), $text) === 1;
    }

    // ── articles (`articles`, `article_sound`) ───────────────────────────────────────────────────────────────

    public function isArticle(string $token): bool
    {
        return $this->pack->listed('articles', $token);
    }

    /** A word a frame and its filler may both say at their seam and still make a sentence («move in in June»). */
    public function isSeamRepeatable(string $token): bool
    {
        return $this->pack->listed('seam_repeatable_words', $token);
    }

    /** An article whose form follows the next word's sound («a» / «an»). */
    public function isSoundArticle(string $token): bool
    {
        $token = LanguagePack::normal($token);

        return $token === mb_strtolower($this->pack->mapString('article_sound', 'before_vowel'))
            || $token === mb_strtolower($this->pack->mapString('article_sound', 'before_consonant'));
    }

    /**
     * What is wrong with `$article` before `$word` (as spelled), by the word's sound — «a» before a vowel, «an»
     * before a consonant — or null. An initialism and the pack's exception are left alone.
     */
    public function articleMismatch(string $article, string $word): ?string
    {
        $article = LanguagePack::normal($article);
        if (preg_match($this->pack->mapString('article_sound', 'spelled'), $word) === 1) {
            return null;
        }
        $lower = LanguagePack::normal($word);
        if (preg_match($this->pack->mapString('article_sound', 'exception'), $lower) === 1) {
            return null;
        }
        if ($article === mb_strtolower($this->pack->mapString('article_sound', 'before_consonant'))
            && preg_match($this->pack->mapString('article_sound', 'vowel'), $lower) === 1) {
            return "«{$article} {$lower}» before a vowel";
        }
        if ($article === mb_strtolower($this->pack->mapString('article_sound', 'before_vowel'))
            && preg_match($this->pack->mapString('article_sound', 'consonant'), $lower) === 1) {
            return "«{$article} {$lower}» before a consonant";
        }

        return null;
    }

    // ── clauses (`clause`) ───────────────────────────────────────────────────────────────────────────────────

    /**
     * Is a filler a clause rather than a value: {@see SENTENCE} when a subject and its verb open it, {@see CLAUSE}
     * when a subordinator opens it (an ambiguous one only before a subject — «after he eats», never «after meals»)
     * or a subject and its verb stand inside it; null for a value.
     */
    public function clause(string $text): ?string
    {
        $tokens = array_map(LanguagePack::normal(...), Words::tokens($text));
        if ($tokens === []) {
            return null;
        }
        $subjects = $this->pack->mapWords('clause', 'subjects');
        $finite = $this->pack->mapWords('clause', 'finite');
        $contractions = $this->pack->mapWords('clause', 'contractions');
        $subjectAt = static fn (int $i): bool => in_array($tokens[$i] ?? '', $contractions, true)
            || (in_array($tokens[$i] ?? '', $subjects, true) && in_array($tokens[$i + 1] ?? '', $finite, true));

        if ($subjectAt(0)) {
            return self::SENTENCE;
        }
        if (count($tokens) > 1 && in_array($tokens[0], $this->pack->mapWords('clause', 'subordinators'), true)) {
            return self::CLAUSE;
        }
        if (in_array($tokens[0], $this->pack->mapWords('clause', 'subordinators_before_subject'), true)
            && (in_array($tokens[1] ?? '', $subjects, true) || in_array($tokens[1] ?? '', $contractions, true))) {
            return self::CLAUSE;
        }
        for ($i = 1; $i < count($tokens); $i++) {
            if ($subjectAt($i)) {
                return self::CLAUSE;
            }
        }

        return null;
    }

    // ── pronouns a frame leans on (`unresolved_pronouns`, `function_words`) ─────────────────────────────────

    /** The pronoun a frame leans on with nothing in the frame it stands for, or null. */
    public function unresolvedPronoun(string $frame): ?string
    {
        $parts = preg_split(FrameText::SLOT_PATTERN, $frame, 2) ?: [$frame];
        $tokens = Words::tokens($parts[0]);
        if (count($parts) > 1) {
            $tokens = [...$tokens, self::SLOT, ...Words::tokens($parts[1])];
        }
        $tokens = array_map(LanguagePack::normal(...), $tokens);
        $pronouns = $this->pack->mapWords('unresolved_pronouns', 'words');
        $initial = $this->pack->mapWords('unresolved_pronouns', 'frame_initial_subject');
        $existential = $this->pack->mapWords('unresolved_pronouns', 'existential');
        $determinerOrNumber = $this->pack->mapWords('unresolved_pronouns', 'determiner_or_number');
        $partitive = $this->pack->mapWords('unresolved_pronouns', 'partitive');
        $be = $this->pack->mapWords('unresolved_pronouns', 'be_forms');
        $determiners = $this->pack->mapWords('unresolved_pronouns', 'determiners');

        foreach ($tokens as $i => $word) {
            if (! in_array($word, $pronouns, true)) {
                continue;
            }
            $previous = $tokens[$i - 1] ?? null;
            $next = $tokens[$i + 1] ?? null;
            if ($i === 0 && in_array($word, $initial, true)) {
                continue;
            }
            if (in_array($word, $existential, true) && (in_array($previous, $be, true) || in_array($next, $be, true))) {
                continue;
            }
            if (in_array($word, $determinerOrNumber, true) && $next !== null
                && ($next === self::SLOT || in_array($next, $partitive, true) || ! $this->isFunction($next))) {
                continue;
            }
            for ($j = 0; $j + 1 < $i; $j++) {
                if (in_array($tokens[$j], $determiners, true) && $tokens[$j + 1] !== self::SLOT && ! $this->isFunction($tokens[$j + 1])) {
                    continue 2;
                }
            }

            return $word;
        }

        return null;
    }

    // ── the learner's own language (`script`, `gendered_past_pattern`, `agreement`) ─────────────────────────

    /** Is a reading written only in the letters the learner reads (`script`)? */
    public function readsInScript(string $reading): bool
    {
        return preg_match($this->pack->pattern('script'), $reading) === 1;
    }

    /**
     * The LETTERS of the reading that belong to another writing (`script_letters`, наряд BACK-TAILS-1 §3.2) — each one
     * once, in the order they stand. Only letters are looked at: a digit or a mark is a matter for `script`, and the
     * one thing a learner cannot do is read an alphabet they do not know («ֆоутoуз» — an Armenian ֆ and two Latin o's
     * among the Cyrillic).
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

    /**
     * The words of a native frame that agree with its slot (`agreement`): the word right before `___` when it is
     * listed or an adjective by its ending, and a listed word among the few right after `___`.
     *
     * @return list<string>
     */
    public function agreeingWithSlot(string $frameNative): array
    {
        $parts = preg_split(FrameText::SLOT_PATTERN, $frameNative, 2);
        if (! is_array($parts) || count($parts) < 2) {
            return [];
        }
        $listed = [...$this->pack->mapWords('agreement', 'words'), ...$this->pack->mapWords('agreement', 'short_forms')];
        $suffixes = $this->pack->mapWords('agreement', 'suffixes_before_slot');
        $minLetters = $this->pack->mapInt('agreement', 'min_letters');
        $window = $this->pack->mapInt('agreement', 'after_slot_words');

        $out = [];
        $before = array_map(LanguagePack::normal(...), Words::tokens($parts[0]));
        $left = $before === [] ? null : $before[count($before) - 1];
        if ($left !== null && (in_array($left, $listed, true)
            || (mb_strlen($left) >= $minLetters && array_filter($suffixes, static fn (string $s): bool => str_ends_with($left, $s)) !== []))) {
            $out[] = $left;
        }
        foreach (array_slice(array_map(LanguagePack::normal(...), Words::tokens($parts[1])), 0, $window) as $word) {
            if (in_array($word, $listed, true)) {
                $out[] = $word;
            }
        }

        return array_values(array_unique($out));
    }
}
