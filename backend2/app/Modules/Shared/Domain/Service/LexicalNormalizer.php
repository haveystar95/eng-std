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
    /** Lowercase, expand contractions, punctuation → space, whitespace collapsed, article dropped. */
    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = $this->expandContractions($value);            // before punctuation strips the apostrophe
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $this->stripArticle(trim($value));
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
    private function expandContractions(string $value): string
    {
        $value = str_replace(['’', '`'], "'", $value); // normalise apostrophe glyphs first
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
}
