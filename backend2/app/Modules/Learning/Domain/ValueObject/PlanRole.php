<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The one person on the other side of a SCENE's conversation.
 *
 * It carried the scene's checkpoints until v0.2, in a list kept parallel to the abilities by
 * nothing but discipline — and a scene with no interlocutor therefore had no checkpoints at all,
 * which said that an ability nobody watches cannot be checked. Since v0.2 the checkpoint belongs to
 * the ability it proves ({@see PlanSkill}) and this object is only the person.
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
     *         directions — a role the learner cannot hear is not a role. P2 quotes these VERBATIM
     *         as the lines the learner must recognise, so they are content and not colour.
     */
    public function __construct(
        public string $name,
        public array $openingLines,
        public string $ifSilent,
    ) {}
}
