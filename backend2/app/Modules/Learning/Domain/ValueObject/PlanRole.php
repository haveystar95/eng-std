<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The one person on the other side of a day's conversation.
 *
 * NULL is a legitimate answer for a day that genuinely has nobody to talk to (reading forms, labels
 * or signs), and the prompt is explicit that inventing «сотрудник, который просто рядом» is worse
 * than admitting it. So every consumer here has to cope with the absence — which is why this is a
 * separate object rather than four nullable fields on the day.
 */
final readonly class PlanRole
{
    /**
     * @param  list<array{text: string, translation: string}>  $openingLines  what this person
     *         actually SAYS, in order, each with its support-language gloss. Utterances, not stage
     *         directions — a role the learner cannot hear is not a role.
     * @param  list<string>  $checkpoints  what has to be HEARD for the day's promises to count.
     *         One per outcome line, in the same order, and never a copy of it.
     */
    public function __construct(
        public string $name,
        public array $openingLines,
        public array $checkpoints,
        public string $ifSilent,
    ) {}
}
