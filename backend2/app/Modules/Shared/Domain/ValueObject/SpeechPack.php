<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * WHAT A COMPARISON OF SPEECH HAS TO KNOW ABOUT ONE LANGUAGE (наряд FIX-2, п. 2) — a few lists, and nothing about
 * the rule that reads them.
 *
 * The kernel cannot read a module's language pack (deptrac), and it must not hard-code English: «a/an/the» is not
 * a fact about speech, it is a fact about English. So the module that HAS the pack hands these lists down, and a
 * language that names none of them gets {@see none()} — a comparison that forgives nothing, which is the right
 * answer for a language nobody has written a pack for yet.
 *
 * Every list of WORDS is in the canonical form of the text it is matched against — the form
 * {@see \App\Modules\Shared\Domain\Service\SpeechMatch::words()} gives a text (folded: NFC, «ß» as «ss», «ş» as «ș»;
 * lower case; no apostrophe; a hyphen a space): the side that builds the pack puts it there (наряд LANG-1 §4), so the
 * rule compares words with words and never spells anything itself.
 *
 * - `unstressed` — the words a recogniser eats: articles, prepositions, auxiliary verbs. They are left out of BOTH
 *   sides of a `repeat` comparison ({@see SpeechMode::Repeat}), in the expectation and in what was heard;
 * - `articles` — the narrower list the `free` comparison forgives, where the bar is a SHARE of the words and
 *   dropping every function word would leave nothing to count;
 * - `abbreviations` — «p.m.», «Mr.»: written with dots that are not sentence ends and not word breaks, so they are
 *   folded to their letters before anything else looks at them («p.m.» → `pm`, and «3 p.m.» is two words, not
 *   three). They are searched for in the text AS WRITTEN, letter case aside, so they keep their dots and their case;
 * - `numberWords` — «three» → `3`: a recogniser writes a number either way and the learner said the same thing. The
 *   words of one number are read as one number (`forty five` → `45`, «a hundred» → `100` — an article of `articles`
 *   right before hundred or thousand is one) by the rule of {@see \App\Modules\Shared\Domain\Service\SpokenNumbers}; an
 *   entry may be several words one space apart (fr «quatre vingt dix» → `90`), and the longest one standing is read;
 * - `numberJoiners` — «and» of «one hundred and twenty»: a word that joins a SCALE (hundred, thousand) to the number
 *   below a hundred after it, the way British English says one number;
 * - `numberTensJoiners` — «y» of es «treinta y uno», «și» of ro «douăzeci și unu», «et» of fr «vingt et un»: a word
 *   that joins a TENS value to the unit after it (наряд LANG-1 §4). A list of its own, because English says its «and»
 *   only after a scale — «between twenty and one hundred» is two numbers — and one list read in both places read it as
 *   one.
 */
final readonly class SpeechPack
{
    /**
     * @param  list<string>  $unstressed  canonical, in the form of {@see \App\Modules\Shared\Domain\Service\SpeechMatch::words()}
     * @param  list<string>  $articles  canonical
     * @param  list<string>  $abbreviations  as they are WRITTEN, with their dots («p.m.»)
     * @param  array<string, string>  $numberWords  a canonical entry (a word, or several one space apart) → the digits it says
     * @param  list<string>  $numberJoiners  canonical — the joiners after a scale
     * @param  list<string>  $numberTensJoiners  canonical — the joiners after a tens value
     */
    public function __construct(
        public array $unstressed = [],
        public array $articles = [],
        public array $abbreviations = [],
        public array $numberWords = [],
        public array $numberJoiners = [],
        public array $numberTensJoiners = [],
    ) {}

    /** A language nothing is known about: every word counts, nothing is folded. */
    public static function none(): self
    {
        return new self;
    }

    /**
     * The shape the phone is served. `number_tens_joiners` is OPTIONAL on the wire (наряд LANG-1 §4): it goes out only
     * when the language has one — the phone reads an absent key as none — so the block of a language without them (en,
     * ru) is the very block it was before the key existed.
     *
     * @return array{unstressed_words: list<string>, articles: list<string>, abbreviations: list<string>, number_words: array<string, string>, number_joiners: list<string>, number_tens_joiners?: list<string>}
     */
    public function toArray(): array
    {
        $out = [
            'unstressed_words' => $this->unstressed,
            'articles' => $this->articles,
            'abbreviations' => $this->abbreviations,
            'number_words' => $this->numberWords,
            'number_joiners' => $this->numberJoiners,
        ];
        if ($this->numberTensJoiners !== []) {
            $out['number_tens_joiners'] = $this->numberTensJoiners;
        }

        return $out;
    }
}
