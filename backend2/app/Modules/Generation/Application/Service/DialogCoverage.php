<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\TargetWordView;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * Decides which target words are "covered". Two rules, by term shape:
 *   - MULTI-WORD terms (phrases/questions the agent poses) count when ANY speaker said them —
 *     the agent voicing the phrase is the point, the learner only has to understand it;
 *   - SINGLE-WORD terms count only when the LEARNER (user) produced them in their own reply.
 * Normalisation is the SAME kernel service the answer grader uses, so "the same words" means one
 * thing across the app. Coverage is monotonic: recomputed over all lines so far, and a form once
 * matched stays matched as more lines arrive.
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
        // One normalised, space-padded haystack per line — padding makes the substring test
        // word-boundary aware, so "cash" does not match inside "cashier". Kept split by role so a
        // single-word target can be restricted to the learner's own lines.
        $userHaystacks = [];
        $allHaystacks = [];
        foreach ($lines as $line) {
            $haystack = ' ' . $this->normalizer->normalize($line->text) . ' ';
            $allHaystacks[] = $haystack;
            if ($line->role->isUser()) {
                $userHaystacks[] = $haystack;
            }
        }

        $out = [];
        foreach ($targetWords as $tw) {
            // Multi-word → any speaker counts; single word → the learner must produce it.
            $haystacks = $this->isMultiWord($tw['text']) ? $allHaystacks : $userHaystacks;
            $out[] = new TargetWordView(
                termId: $tw['term_id'],
                text: $tw['text'],
                used: $this->isUsed($tw['forms'], $haystacks),
            );
        }

        return $out;
    }

    private function isMultiWord(string $text): bool
    {
        return str_contains(trim($this->normalizer->normalize($text)), ' ');
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
