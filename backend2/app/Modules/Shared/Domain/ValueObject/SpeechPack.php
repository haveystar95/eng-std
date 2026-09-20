<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

/**
 * WHAT A COMPARISON OF SPEECH HAS TO KNOW ABOUT ONE LANGUAGE (наряд FIX-2, п. 2) — four lists, and nothing about
 * the rule that reads them.
 *
 * The kernel cannot read a module's language pack (deptrac), and it must not hard-code English: «a/an/the» is not
 * a fact about speech, it is a fact about English. So the module that HAS the pack hands these lists down, and a
 * language that names none of them gets {@see none()} — a comparison that forgives nothing, which is the right
 * answer for a language nobody has written a pack for yet.
 *
 * - `unstressed` — the words a recogniser eats: articles, prepositions, auxiliary verbs. They are left out of BOTH
 *   sides of a `repeat` comparison ({@see SpeechMode::Repeat}), in the expectation and in what was heard;
 * - `articles` — the narrower list the `free` comparison forgives, where the bar is a SHARE of the words and
 *   dropping every function word would leave nothing to count;
 * - `abbreviations` — «p.m.», «Mr.»: written with dots that are not sentence ends and not word breaks, so they are
 *   folded to their letters before anything else looks at them («p.m.» → `pm`, and «3 p.m.» is two words, not
 *   three);
 * - `numberWords` — «three» → `3`: a recogniser writes a number either way and the learner said the same thing.
 */
final readonly class SpeechPack
{
    /**
     * @param  list<string>  $unstressed  lower-cased, in the canonical form of {@see \App\Modules\Shared\Domain\Service\SpeechMatch::words()}
     * @param  list<string>  $articles  lower-cased, canonical
     * @param  list<string>  $abbreviations  as they are WRITTEN, with their dots («p.m.»)
     * @param  array<string, string>  $numberWords  lower-cased word → the digits it says
     */
    public function __construct(
        public array $unstressed = [],
        public array $articles = [],
        public array $abbreviations = [],
        public array $numberWords = [],
    ) {}

    /** A language nothing is known about: every word counts, nothing is folded. */
    public static function none(): self
    {
        return new self;
    }

    /** @return array{unstressed_words: list<string>, articles: list<string>, abbreviations: list<string>, number_words: array<string, string>} the shape the phone is served */
    public function toArray(): array
    {
        return [
            'unstressed_words' => $this->unstressed,
            'articles' => $this->articles,
            'abbreviations' => $this->abbreviations,
            'number_words' => $this->numberWords,
        ];
    }
}
