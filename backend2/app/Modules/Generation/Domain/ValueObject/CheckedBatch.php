<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One provider's whole answer, judged: a verdict per item plus the failures that belong to the
 * answer rather than to any item in it (today: the item count).
 *
 * The split matters for the arithmetic. Counting a short answer as one defect per missing item
 * would let a provider that returned 3 items instead of 12 look BETTER than one that returned 12
 * with two flaws in them.
 */
final readonly class CheckedBatch
{
    /**
     * @param  list<CandidateVerdict>  $verdicts  in answer order
     * @param  list<CheckId>  $batchFailures
     */
    public function __construct(
        public array $verdicts,
        public array $batchFailures = [],
        public ?string $sizeNote = null,
    ) {}

    public function total(): int
    {
        return count($this->verdicts);
    }

    public function clean(): int
    {
        return count(array_filter($this->verdicts, static fn (CandidateVerdict $v): bool => $v->isClean()));
    }

    /** How many items failed this particular check. */
    public function failures(CheckId $check): int
    {
        return count(array_filter($this->verdicts, static fn (CandidateVerdict $v): bool => $v->failed($check)));
    }

    /**
     * The defect rate in the first half of the answer versus the second — the tail-quality question.
     *
     * "Does a long answer get sloppier towards the end" is the whole reason a one-shot pipeline
     * might be the wrong shape, and it is invisible in a single average. An odd count puts the
     * middle item in the FIRST half, so the halves never overlap.
     *
     * @return array{0: float, 1: float, 2: int, 3: int}  [first-half rate, second-half rate, n1, n2]
     */
    public function halves(): array
    {
        $n = $this->total();
        if ($n < 2) {
            return [0.0, 0.0, $n, 0];
        }

        $cut = (int) ceil($n / 2);
        $first = array_slice($this->verdicts, 0, $cut);
        $second = array_slice($this->verdicts, $cut);

        return [
            $this->rate($first),
            $this->rate($second),
            count($first),
            count($second),
        ];
    }

    /** @param list<CandidateVerdict> $verdicts */
    private function rate(array $verdicts): float
    {
        if ($verdicts === []) {
            return 0.0;
        }
        $bad = count(array_filter($verdicts, static fn (CandidateVerdict $v): bool => ! $v->isClean()));

        return $bad / count($verdicts);
    }
}
