<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * THE ENGLISH WORD LISTS THE LESSON VALIDATOR READS — short, closed, and heuristic on purpose.
 *
 * They answer four questions a rule asks about English text: is this a function word (a key needs a
 * content word; a rescue may repeat function words freely), is this a plain everyday word or a word
 * of the prompt's STOP LIST (not vocabulary), is this an ordinary adjective or quantifier (the head of
 * a free combination like «heavy things»), and is this line an empty closer («Great!»). A list is a
 * counter's reading of the rule, not the rule: every code built on it is named heuristic in the canon.
 */
final class EnglishWords
{
    private const FUNCTION = [
        'a', 'an', 'the', 'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'up', 'down', 'about',
        'into', 'over', 'after', 'before', 'under', 'and', 'or', 'but', 'so', 'if', 'than', 'as', 'because',
        'while', 'then', 'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did',
        'have', 'has', 'had', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
        'my', 'your', 'his', 'its', 'our', 'their', 'this', 'that', 'these', 'those', 'there', 'here',
        'can', 'could', 'will', 'would', 'should', 'shall', 'may', 'might', 'must', 'not', 'no', 'yes',
        'please', 'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how', 'some', 'any',
        'just', 'very', 'too', 'also', 'all', 'each', 'every', 'both', 'either', 'neither', 'one', 'ones',
        "don't", "doesn't", "didn't", "isn't", "aren't", "wasn't", "weren't", "can't", "won't", "i'm",
        "i've", "i'll", "i'd", "it's", "you're", "we're", "they're", "let's", "that's", "there's",
        'okay', 'ok', 'well', 'oh', 'sorry', 'thanks', 'thank', 'sure', 'let',
    ];

    private const EVERYDAY = [
        // STOP LIST: numbers, family, time words, colours, be / have / go.
        'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'twenty', 'thirty', 'forty', 'fifty', 'hundred', 'thousand', 'first', 'second', 'third',
        'mother', 'father', 'mom', 'mum', 'dad', 'parent', 'parents', 'brother', 'sister', 'son', 'daughter',
        'child', 'children', 'kid', 'kids', 'baby', 'family', 'husband', 'wife', 'grandmother', 'grandfather',
        'day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years', 'today', 'tomorrow', 'yesterday',
        'morning', 'evening', 'night', 'time', 'hour', 'hours', 'minute', 'minutes', 'now', 'later', 'soon',
        'red', 'blue', 'green', 'yellow', 'black', 'white', 'brown', 'grey', 'gray', 'orange', 'pink', 'purple',
        'be', 'is', 'am', 'are', 'was', 'were', 'have', 'has', 'had', 'go', 'goes', 'went', 'gone',
        // Plain words a learner knows at any level of the plan.
        'work', 'house', 'home', 'school', 'man', 'woman', 'men', 'women', 'people', 'person', 'friend',
        'food', 'water', 'car', 'room', 'door', 'table', 'name', 'thing', 'things', 'good', 'bad', 'big',
        'small', 'new', 'old', 'hello', 'eat', 'drink', 'see', 'come', 'get', 'make', 'take', 'give', 'want',
        'like', 'know', 'think', 'say', 'tell', 'look', 'need', 'help', 'place', 'city', 'street', 'money',
        'book', 'phone', 'job', 'dog', 'cat', 'hand', 'head', 'eye', 'eyes', 'problem', 'question', 'answer',
    ];

    private const ORDINARY_HEADS = [
        'big', 'small', 'little', 'good', 'bad', 'nice', 'great', 'heavy', 'many', 'much', 'lot', 'few',
        'some', 'other', 'different', 'important', 'real', 'whole',
    ];

    private const NUMBERS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty', 'thirty',
        'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred', 'thousand', 'million', 'half', 'dozen',
        'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'once', 'twice',
    ];

    private const CLOSERS = [
        'anything else', 'is there anything else', 'great', 'sounds good', 'sounds great', 'perfect', 'okay',
        'ok', 'all right', 'alright', 'good', 'fine', 'thank you', 'thanks', "you're welcome", 'no problem',
        'sure', 'of course', 'have a nice day', 'see you', 'got it', 'excellent', 'wonderful', 'nice',
        "that's great", "that's fine", 'no worries',
    ];

    public static function isFunction(string $token): bool
    {
        return in_array(self::normal($token), self::FUNCTION, true);
    }

    public static function isEveryday(string $token): bool
    {
        return in_array(self::normal($token), self::EVERYDAY, true);
    }

    public static function isOrdinaryHead(string $token): bool
    {
        return in_array(self::normal($token), self::ORDINARY_HEADS, true);
    }

    /** A number: digits anywhere in the word («2», «400», «14a»), a number word, or number words hyphenated («thirty-nine»). */
    public static function isNumber(string $token): bool
    {
        $token = self::normal($token);
        if (preg_match('/\d/u', $token) === 1) {
            return true;
        }
        $parts = explode('-', $token);

        return $parts !== [''] && array_diff($parts, self::NUMBERS) === [];
    }

    /**
     * The names of a text: words written with a capital letter anywhere but at the start of a sentence
     * («Take Nurofen», «Dr Smith»), lower-cased. The pronoun «I» is a function word, not a name.
     *
     * @return list<string>
     */
    public static function names(string $text): array
    {
        $out = [];
        foreach (preg_split('/[.!?…]+/u', $text) ?: [] as $sentence) {
            foreach (array_slice(Words::surface($sentence), 1) as $word) {
                if (preg_match('/^\p{Lu}/u', $word) === 1 && ! self::isFunction($word)) {
                    $out[] = mb_strtolower($word);
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** A line that says nothing but «we are done» — its whole text, punctuation aside. */
    public static function isCloser(string $line): bool
    {
        return in_array(implode(' ', array_map(self::normal(...), Words::tokens($line))), self::CLOSERS, true);
    }

    /**
     * The content words of a text — tokens that are not function words.
     *
     * @return list<string>
     */
    public static function content(string $text): array
    {
        return array_values(array_filter(Words::tokens($text), static fn (string $t): bool => ! self::isFunction($t)));
    }

    /**
     * One word, or two forms of it: the shorter's letters but its last three, and never fewer than
     * three, shared from the start («heat» — «heating», «rest» — «resting», «use» — «used»).
     */
    public static function sameStem(string $a, string $b): bool
    {
        $a = self::normal($a);
        $b = self::normal($b);
        if ($a === $b) {
            return true;
        }
        $min = min(mb_strlen($a), mb_strlen($b));
        if ($min < 3) {
            return false;
        }
        $prefix = 0;
        while ($prefix < $min && mb_substr($a, $prefix, 1) === mb_substr($b, $prefix, 1)) {
            $prefix++;
        }

        return $prefix >= max(3, $min - 3);
    }

    /**
     * The content words of `$a` that have no word of the same stem among the words of `$b`.
     *
     * @return list<string>
     */
    public static function notIn(string $a, string $b): array
    {
        $theirs = Words::tokens($b);
        $out = [];
        foreach (array_unique(self::content($a)) as $word) {
            $found = false;
            foreach ($theirs as $other) {
                if (self::sameStem($word, $other)) {
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

    /** How many content words of `$a` have a word of the same stem in `$b`. */
    public static function shared(string $a, string $b): int
    {
        return count(array_unique(self::content($a))) - count(self::notIn($a, $b));
    }

    private static function normal(string $token): string
    {
        return str_replace('’', "'", mb_strtolower(trim($token)));
    }
}
