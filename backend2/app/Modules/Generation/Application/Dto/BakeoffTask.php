<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\BakeoffTrack;

/**
 * One unit of work, handed IDENTICALLY to every provider in a track.
 *
 * Identical is the whole point and the reason this is a value rather than a closure built per
 * provider: a comparison where one vendor got a slightly different topic string, or a different
 * subset of terms, measures the difference in the tasks and reports it as a difference in the models.
 */
final readonly class BakeoffTask
{
    /**
     * @param  string  $key  what this task is, for the report and for grouping calls: a topic, or a
     *                       term's own text
     * @param  string  $userMessage  the delimited data block — never phrased as an instruction
     * @param  int|null  $expectedSize  how many items the answer must have, when that is knowable
     * @param  list<array{id: string, text: string}>  $terms  the given terms, on the enrichment track
     */
    public function __construct(
        public BakeoffTrack $track,
        public string $key,
        public string $userMessage,
        public ?int $expectedSize = null,
        public array $terms = [],
    ) {}
}
