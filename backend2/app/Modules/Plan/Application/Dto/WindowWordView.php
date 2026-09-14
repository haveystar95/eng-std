<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * A word or a chunk card of the window and its sheet (23-0e): its photo when it has one and always the
 * tone its slot is painted with; how it reads in the learner's alphabet, what it means, its voice, the
 * line of the day it is said in, and — when it comes back — the day it comes back on (DAY-UI-3).
 */
final readonly class WindowWordView
{
    /**
     * @param  array{url: string, author: string|null, author_url: string|null, tone: string|null}|null  $image
     * @param  list<string>  $usedIn  where the lesson says it — frame ids and partner lines (GEN-2a)
     */
    public function __construct(
        public string $ref,
        public string $term,
        public string $translation,
        public ?array $image,
        public string $imageTone,
        public string $state,
        public ?string $pronunciation = null,
        public ?string $definition = null,
        public ?string $audioId = null,
        public ?WindowUsageView $usage = null,
        public ?int $returnsDay = null,
        public array $usedIn = [],
    ) {}
}
