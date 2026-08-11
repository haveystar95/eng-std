<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * What survived validation for one term, plus what the run should count. `rejectedDistractors` is
 * kept as a NUMBER rather than dropped silently, because the run's headline metric is the scrap
 * rate — a станок that quietly discards half its output looks identical to one that works.
 */
final readonly class EnrichmentVerdict
{
    /**
     * @param  list<RawDistractor>  $distractors  validated, safe to write
     * @param  list<RawVariant>  $variants  validated, safe to write
     * @param  list<EnrichmentFinding>  $findings
     */
    public function __construct(
        public string $termId,
        public array $distractors,
        public array $variants,
        public array $findings,
        public int $proposedDistractors,
        public int $rejectedDistractors,
    ) {}

    public function hasFinding(FindingKind $kind): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->kind === $kind) {
                return true;
            }
        }

        return false;
    }
}
