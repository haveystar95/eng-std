<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One of a term's examples. A term can accumulate several (each generation pass appends one), but
 * exactly one is PINNED — the lowest id — and that is the only one the card and the client ever
 * show, the only one distractors hang off. The admin page shows the rest too, because "why is the
 * app showing that sentence" is unanswerable if you can only see the one it picked.
 *
 * @param  list<ExampleDistractorRow>  $distractors
 */
final readonly class TermExampleRow
{
    /** @param list<ExampleDistractorRow> $distractors */
    public function __construct(
        public string $id,
        public string $sentence,
        public ?string $translation,
        public bool $isPinned,
        public array $distractors = [],
    ) {}
}
