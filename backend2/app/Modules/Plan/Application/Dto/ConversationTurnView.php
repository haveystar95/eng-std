<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE LINE OF THE RIBBON (кадры 37-6…37-12). The role's line comes with both texts and its sound;
 * the learner's with what was heard and the targets the server heard in it — by scene and ref: what a target is and
 * what went into its window is in `targets[]` (наряд FIX-3 §6); the other constructions of its scene it said are
 * `extraSaid` (наряд FIX-4 §2). A rescue carries «Sorry?» in the language of the talk (п. 4а). Every line names the scene
 * it was said in, and in a talk over several scenes the role's greeting and goodbye say so (`sceneEvent`, наряд FIX-4 §4).
 */
final readonly class ConversationTurnView
{
    /**
     * @param  list<array{scene_id: string, ref: string}>  $phrasesUsed  the targets this line said
     * @param  list<array{scene_id: string, ref: string}>  $extraSaid  the other constructions of its scene it said
     */
    public function __construct(
        public int $index,
        public string $speaker,
        public string $kind,
        public ?string $textTarget,
        public ?string $textNative,
        public ?string $audioId,
        public ?int $audioDurationMs,
        public ?bool $understood,
        public array $phrasesUsed,
        public ?bool $offTopic,
        public string $createdAt,
        public ?string $sceneId = null,
        public ?string $sceneEvent = null,
        public array $extraSaid = [],
    ) {}
}
