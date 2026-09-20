<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE LINE OF THE RIBBON (кадры 37-6…37-12). The role's line comes with both texts and its sound;
 * the learner's with what was heard and the phrases of the plan the server matched in it — that is
 * what the sage underline under a phrase of the day is drawn from.
 */
final readonly class ConversationTurnView
{
    /** @param list<array{scene_id: string, ref: string}> $phrasesUsed */
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
    ) {}
}
