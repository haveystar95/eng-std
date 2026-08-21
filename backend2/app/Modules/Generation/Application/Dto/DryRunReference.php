<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * The reference a dry run judges distractors against — the same three facts the станок feeds the
 * validator, plus the provenance the validator does not need and a person does.
 *
 * `existingDistractors` is passed to the validator VERBATIM and is the only list that decides
 * anything: it is what production builds (rows stored against this example, sentences a proofreader
 * or the audit suppressed, and the same two for sibling terms whose text normalises identically).
 * `stored` and `suppressed` are hints, used ONLY to say WHICH of those a duplicate matched. Keeping
 * them apart is what stops the sandbox from quietly judging against a different set than production
 * — the labelling can be wrong about the source and the verdict is still the real one.
 */
final readonly class DryRunReference
{
    /**
     * @param  list<string>  $acceptedForms  the term's own text first, then stored variants
     * @param  list<string>  $existingDistractors  prod-identical dedup set
     * @param  list<string>  $stored  sentences currently stored against the pinned example
     * @param  list<string>  $suppressed  sentences removed by a review or the audit
     */
    public function __construct(
        public ?string $termId,
        public string $termText,
        public array $acceptedForms,
        public ?string $exampleId,
        public ?string $exampleSentence,
        public array $existingDistractors = [],
        public array $stored = [],
        public array $suppressed = [],
    ) {}
}
