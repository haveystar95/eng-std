<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One entry of the learner's live event feed: an answer, or a word being introduced.
 *
 * Two append-only logs are merged here because that is what a session LOOKS like from outside —
 * intro, answer, answer, intro. They stay separate tables for good reason (an intro asks nothing, so
 * it must never inflate retention or the latency medians; see `term_exposures`), and this feed is
 * the one place they are read as a single stream.
 *
 * ORDERING: this is a display feed and it is ordered by TIME, which is the only thing the two logs
 * share. That is not a hole in the client_seq rule — nothing is derived from this order. Where the
 * order decides something, the reviews are read by `client_seq` (see {@see AdminLadderReview}), and
 * `clientSeq` is carried here so a feed and a verdict can always be reconciled by eye.
 */
final readonly class AdminLadderEvent
{
    public const KIND_REVIEW = 'review';
    public const KIND_EXPOSURE = 'exposure';

    public function __construct(
        public string $id,
        /** `review` | `exposure` */
        public string $kind,
        public string $termId,
        public ?string $termText,
        public ?string $occurredAt,
        public ?string $exerciseMode,
        public ?string $grade,
        public ?bool $isCorrect,
        public bool $isPractice,
        /** The rung the card was dealt at. Always null for an intro — an intro is rung 0 by nature. */
        public ?int $ladderStep,
        public ?string $response,
        public ?int $clientSeq,
    ) {}
}
