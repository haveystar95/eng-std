<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * What a repair of one card came back with (P2R). `status`: `repaired` — the model answered with a card of
 * the right shape and `answer` is the lesson with it put in; `nothing_to_repair` — the validator finds
 * nothing at the address (and no code was named); `not_a_card` — the address names no repairable card;
 * `off_schema` — the model's card is not the card's shape, or an exchange's `frame_update` does not fit it, or a learner
 * line names no frame of the lesson (the call is paid for all the same, and nothing is put in); `refused` — a repaired
 * word the server's own check does not take: `used_in` untrue, a word of an earlier day, a word twice in the day (P2R
 * v1.2, наряд GEN-3; paid for, nothing put in, `note` says why). `frameUpdate` — the frame a repaired exchange came with.
 */
final readonly class LessonCardRepairOutcome
{
    public const REPAIRED = 'repaired';

    public const NOTHING_TO_REPAIR = 'nothing_to_repair';

    public const NOT_A_CARD = 'not_a_card';

    public const OFF_SCHEMA = 'off_schema';

    public const REFUSED = 'refused';

    /**
     * @param  array<string, mixed>|null  $before  the card as the answer held it
     * @param  array<string, mixed>|null  $after  the card the model returned
     * @param  list<array{code: string, address: string, detail: string}>  $findingsBefore  at this card, before
     * @param  list<array{code: string, address: string, detail: string}>  $findingsAfter  at this card, after
     * @param  list<array{code: string, address: string, detail: string}>  $lessonFindings  over the whole repaired lesson
     * @param  array<string, mixed>|null  $frameUpdate  the frame a repaired exchange came with
     */
    public function __construct(
        public string $status,
        public string $address,
        public ?string $kind,
        public ?array $before,
        public ?array $after,
        public array $findingsBefore,
        public array $findingsAfter,
        public ?Lesson $answer,
        public array $lessonFindings,
        public int $lessonFindingsBefore,
        public string $costUsd,
        public int $latencyMs,
        public string $promptVersion,
        public string $note = '',
        public ?array $frameUpdate = null,
    ) {}

    /**
     * The same outcome with more findings over the repaired lesson — the judged ones a stored lesson keeps.
     *
     * @param  list<array{code: string, address: string, detail: string}>  $findings
     */
    public function withLessonFindings(array $findings): self
    {
        return new self(
            $this->status, $this->address, $this->kind, $this->before, $this->after, $this->findingsBefore, $this->findingsAfter,
            $this->answer, [...$this->lessonFindings, ...$findings], $this->lessonFindingsBefore, $this->costUsd, $this->latencyMs,
            $this->promptVersion, $this->note, $this->frameUpdate,
        );
    }
}
