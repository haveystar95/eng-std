<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * WHY a (user, term) pair is in the pool — every reason, not the latest one.
 *
 * A list rather than a column, and the case that decides it: a word the learner saved by hand in
 * June and that a plan needs in August has TWO reasons to be studied. Finishing the plan must take
 * away the plan's reason and leave the learner's, and a single `source` column would have to pick
 * one of the two and be wrong about the other.
 *
 * Three shapes, and only three:
 *
 *   manual        a tap — «Учить это слово», or a word saved out of search
 *   triage        a swipe said «не знаю» / «не уверен»
 *   plan:<ULID>   day N of that plan introduced it, and the plan is holding it
 *
 * Order is preserved and duplicates are not: the list is a SET with a stable reading order, so
 * `["manual","plan:01J…"]` and `["plan:01J…","manual"]` are the same fact stored two ways, and
 * this type makes sure only the first spelling ever gets written.
 */
final readonly class EnrollmentSources
{
    public const MANUAL = 'manual';
    public const TRIAGE = 'triage';
    private const PLAN_PREFIX = 'plan:';

    /** @param list<string> $sources */
    private function __construct(public array $sources) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function manual(): self
    {
        return new self([self::MANUAL]);
    }

    public static function triage(): self
    {
        return new self([self::TRIAGE]);
    }

    /** @param array<mixed> $raw as stored in the jsonb column */
    public static function fromArray(array $raw): self
    {
        $out = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $trimmed = trim($item);
            if ($trimmed !== '' && ! in_array($trimmed, $out, true)) {
                $out[] = $trimmed;
            }
        }

        return new self($out);
    }

    public static function forPlan(string $planId): string
    {
        return self::PLAN_PREFIX . $planId;
    }

    public function with(string $source): self
    {
        return in_array($source, $this->sources, true)
            ? $this
            : new self([...$this->sources, $source]);
    }

    public function without(string $source): self
    {
        return new self(array_values(array_filter(
            $this->sources,
            static fn (string $s): bool => $s !== $source,
        )));
    }

    public function has(string $source): bool
    {
        return in_array($source, $this->sources, true);
    }

    /**
     * The plan ids holding this pair, in the order they claimed it.
     *
     * @return list<string>
     */
    public function planIds(): array
    {
        $out = [];
        foreach ($this->sources as $source) {
            if (str_starts_with($source, self::PLAN_PREFIX)) {
                $out[] = substr($source, strlen(self::PLAN_PREFIX));
            }
        }

        return $out;
    }

    public function isEmpty(): bool
    {
        return $this->sources === [];
    }
}
