<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\TargetWordView;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * Decides which target words the learner actually used, by looking for a normalised occurrence of
 * any of a term's forms inside the USER's transcript lines (the assistant saying the word doesn't
 * count). Normalisation is the SAME kernel service the answer grader uses, so "the same words"
 * means one thing across the app. Coverage is monotonic: it is recomputed over all lines so far,
 * and a form once matched stays matched as more lines arrive.
 */
final readonly class DialogCoverage
{
    public function __construct(private LexicalNormalizer $normalizer) {}

    /**
     * @param  list<array{term_id: string, text: string, forms: list<string>}>  $targetWords  from lesson_json
     * @param  list<TranscriptLine>  $lines
     * @return list<TargetWordView>
     */
    public function evaluate(array $targetWords, array $lines): array
    {
        // One normalised, space-padded haystack per user line — padding makes the substring test
        // word-boundary aware, so "cash" does not match inside "cashier".
        $haystacks = [];
        foreach ($lines as $line) {
            if ($line->role->isUser()) {
                $haystacks[] = ' ' . $this->normalizer->normalize($line->text) . ' ';
            }
        }

        $out = [];
        foreach ($targetWords as $tw) {
            $out[] = new TargetWordView(
                termId: $tw['term_id'],
                text: $tw['text'],
                used: $this->isUsed($tw['forms'], $haystacks),
            );
        }

        return $out;
    }

    /**
     * @param  list<string>  $forms
     * @param  list<string>  $haystacks
     */
    private function isUsed(array $forms, array $haystacks): bool
    {
        foreach ($forms as $form) {
            $needle = ' ' . $this->normalizer->normalize($form) . ' ';
            if (trim($needle) === '') {
                continue;
            }
            foreach ($haystacks as $haystack) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
