<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Canonicalises free text for equality comparison: lowercase, expand common English
 * contractions, punctuation → space, whitespace collapsed, leading article dropped.
 *
 * Extracted from the Learning answer grader so the grader and any other consumer (e.g. the
 * practice-dialog coverage check that decides whether a target word was actually used) share
 * ONE definition of "the same words". A copy would drift; a shared kernel service cannot.
 */
final class LexicalNormalizer
{
    public function __construct(private readonly TextNormalizer $unicode = new TextNormalizer()) {}

    /** Lowercase, expand contractions, punctuation → space, whitespace collapsed, article dropped. */
    public function normalize(string $value): string
    {
        return $this->stripArticle($this->canonicalize($value));
    }

    /**
     * The same canonicalisation WITHOUT dropping the leading article — for comparisons where the
     * article is the very thing under examination.
     *
     * {@see normalize()} throws the leading article away because a typed answer should match whether
     * or not the learner wrote "the". That is right for an ANSWER and wrong for a fragment: an
     * `article` distractor's whole content is "bank account" against "a bank account", and normalize()
     * folds those two onto each other — so a check built on it would declare every article correction
     * a no-op and scrap the entire class. 98 of the 101 rows the first run of that check flagged were
     * exactly this.
     */
    public function canonicalize(string $value): string
    {
        return $this->fold($value, keepApostrophe: false);
    }

    /**
     * The same canonicalisation with the APOSTROPHE kept — for the callers comparing two SPELLINGS.
     *
     * {@see canonicalize()} folds «account's» onto «accounts», and that is right for an answer: the
     * mark joins letters rather than separating words, and a learner who leaves it out has written
     * the same word (Д-32). It is wrong for a comparison whose whole subject is the mark. The
     * enrichment validator's «this correction corrects nothing» gate is exactly that: a distractor
     * whose span is «accounts» and whose correction is «account's» is a real repair, and folded
     * together the two look identical and the row is scrapped.
     *
     * The same shape as {@see canonicalize()} against {@see normalize()} one method up, and for the
     * same reason: a comparison must not fold away the thing it is examining.
     */
    public function canonicalizeKeepingApostrophe(string $value): string
    {
        return $this->fold($value, keepApostrophe: true);
    }

    private function fold(string $value, bool $keepApostrophe): string
    {
        // Unicode BEFORE anything else: this is the one comparison point in the product, so the
        // FOLD form belongs here and nowhere else ({@see TextNormalizer}). It is what lets a learner
        // who typed «stiu» with a cedilla, or «strasse» for «Straße», be right — they know the word,
        // and a grader that says otherwise is testing their keyboard. Nothing folded is ever stored:
        // the store keeps the canonical spelling, which is a different method on the same class.
        $value = $this->unicode->fold($value);
        $value = mb_strtolower(trim($value));
        $value = $this->expandContractions($value);            // before the apostrophe is removed
        // THE APOSTROPHE IS REMOVED, NOT SPACED (Д-32).
        //
        // Every other mark becomes a space, which is right: a comma or a full stop separates words.
        // An apostrophe JOINS them, and treating it like the others split «He's» into «he s» — two
        // tokens where the learner sees one. Typing the same sentence without the mark then differed
        // by a single character, so the exact answer «He's five years old.» came back «Почти» with
        // `hard` on the schedule, one edit away from the key it actually matched.
        //
        // Both glyphs, because a phone types the typographic one and the store holds the ASCII one;
        // `expandContractions()` folds them together first, and this is the same list read twice
        // rather than a second opinion about what an apostrophe is.
        $value = $keepApostrophe
            ? str_replace(self::APOSTROPHES, "'", $value)
            : str_replace(self::APOSTROPHES, '', $value);
        $keep = $keepApostrophe ? "'" : '';
        $value = preg_replace('/[^\p{L}\p{N}\s' . $keep . ']+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /** Leading article optional in both directions: "the bank" ↔ "bank". */
    public function stripArticle(string $normalized): string
    {
        return (string) preg_replace('/^(the|a|an)\s+/u', '', $normalized);
    }

    /**
     * Expand the common English contractions so "I'd like to withdraw" matches "I would like to
     * withdraw". A small curated set on purpose (the ambiguous ones like "'d" are the point of the
     * curation); it grows as real answers show what people actually type.
     */
    /**
     * Every glyph a person or a keyboard may write an apostrophe as. The ASCII one is last because
     * the others are folded onto it first.
     */
    private const APOSTROPHES = ['’', '‘', '´', '`', "'"];

    private function expandContractions(string $value): string
    {
        $value = str_replace(['’', '‘', '´', '`'], "'", $value); // normalise apostrophe glyphs first
        $value = $this->expandPerfectAuxiliary($value);
        $map = [
            "i'd" => 'i would', "i'll" => 'i will', "i'm" => 'i am', "i've" => 'i have',
            "you're" => 'you are', "you'd" => 'you would', "you'll" => 'you will', "you've" => 'you have',
            "we're" => 'we are', "we'd" => 'we would', "we'll" => 'we will', "we've" => 'we have',
            "they're" => 'they are', "they'd" => 'they would', "they'll" => 'they will', "they've" => 'they have',
            "it's" => 'it is', "that's" => 'that is', "there's" => 'there is', "let's" => 'let us',
            "don't" => 'do not', "doesn't" => 'does not', "didn't" => 'did not', "isn't" => 'is not',
            "aren't" => 'are not', "wasn't" => 'was not', "weren't" => 'were not', "can't" => 'cannot',
            "won't" => 'will not', "wouldn't" => 'would not', "couldn't" => 'could not', "shouldn't" => 'should not',
            "haven't" => 'have not', "hasn't" => 'has not', "hadn't" => 'had not',
        ];

        return (string) preg_replace_callback(
            "/\b[a-z]+'[a-z]+\b/",
            static fn (array $m): string => $map[$m[0]] ?? $m[0],
            $value,
        );
    }

    /**
     * The one place where `'s` and `'d` are NOT ambiguous: in front of «been».
     *
     * «he's been running» can only be «he has been running» — «he is been» is not English — and
     * «I'd been waiting» can only be «I had been waiting». Everywhere else the reading genuinely is
     * a guess, which is why the curated map picks one and stops there; here the grammar decides, so
     * a deterministic rule is available and the map's guess («it's» → «it is») would be wrong.
     * Hence: applied BEFORE the map, so it wins over the entries that would otherwise fire.
     *
     * Subject-agnostic on purpose — the rule is about the auxiliary, not about who the subject is, so
     * it covers «he's been», «she's been», «the delivery's been» without listing pronouns.
     *
     * The live evidence: a generated distractor «He has been running a temperature since last night.»
     * against the example «He's been running a temperature since last night.» — the SAME sentence,
     * offered to the learner as the wrong answer, because the two spellings did not fold together.
     */
    private function expandPerfectAuxiliary(string $value): string
    {
        $value = preg_replace("/\b([a-z]+)'s(\s+been\b)/u", '$1 has$2', $value) ?? $value;

        return preg_replace("/\b([a-z]+)'d(\s+been\b)/u", '$1 had$2', $value) ?? $value;
    }
}
