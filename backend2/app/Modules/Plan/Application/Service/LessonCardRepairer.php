<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\DialogueExchange;
use App\Modules\Plan\Domain\Lesson\ExchangeCheck;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Lesson\OptionShuffle;
use App\Modules\Plan\Domain\Lesson\PartnerLine;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Lesson\SkeletonFrame;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\FrameText;
use Throwable;

/**
 * THE REPAIR OF ONE CARD (`lesson_card_repair.v1.5`, наряд GEN-4, 3.9) — asked by the day's build for a card a warning of its
 * stage (or the seam judge) stands at.
 *
 * The model is shown the card at its ADDRESS, the FINDINGS at it (code and English detail), the SKELETON whole, the DIALOGUE
 * whole for a card of the dialogue, a whole exchange's NEIGHBOURS, and EARLIER_DAYS in the short form; the rules it is given
 * are the sections of the prompt the card was written with ({@see \App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles::REPAIR_SECTIONS}).
 * It answers with the card; the card is parsed to its shape and put back by {@see LessonCard::replace()} — which keeps what
 * the card may not change whatever the model wrote, and, once the dialogue exists, says a repaired partner line anew in its
 * exchange. The server refuses what the schema cannot say: a learner line on no frame of the skeleton, a frame that drops a
 * filler the dialogue says, a word the day already has. A check, a listening question or a whole exchange is shuffled
 * again by the day's seed ({@see OptionShuffle}): the model's own order is never the served one. Nothing is judged here — the
 * build checks the stage again and keeps the repair only if it breaks nothing fatal.
 */
final readonly class LessonCardRepairer
{
    public function __construct(
        private PlanModelPort $model,
        private LessonParser $parser,
    ) {}

    /** @param list<LessonViolation> $findings the findings at the card */
    public function repair(Skeleton $skeleton, ?Dialogue $dialogue, LessonCard $card, array $findings, LessonRequest $request): LessonCardRepairOutcome
    {
        $before = $card->of($skeleton, $dialogue);
        if ($before === null || (! $card->ofSkeleton() && $dialogue === null)) {
            return new LessonCardRepairOutcome(LessonCardRepairOutcome::NOT_A_CARD, $card->address, $card->kind, $before, null, self::rows($findings), null, null, '0.000000', 0, '', 'no card at this address');
        }

        try {
            $reply = $this->model->repairLessonCard(new LessonCardRepairRequest(
                address: $card->address,
                kind: $card->kind,
                card: $before,
                findings: array_map(static fn (LessonViolation $v): array => ['code' => $v->code, 'detail' => "{$v->address}: {$v->detail}"], $findings),
                skeleton: $skeleton->toArray(),
                dialogue: $card->ofSkeleton() ? null : $dialogue?->toArray(),
                neighbours: $card->kind === LessonCard::EXCHANGE && $dialogue !== null ? self::neighbours($dialogue, $card->number) : null,
                earlierDays: $request->earlierDays,
                targetLanguage: $request->targetLanguage,
                nativeLanguage: $request->nativeLanguage,
                level: $request->level,
                learnerGender: $request->learnerGender,
            ));
        } catch (PlanModelUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PlanModelUnavailable::because($e->getMessage());
        }

        $raw = $reply->payload['card'] ?? null;
        try {
            if (! is_array($raw)) {
                throw ModelAnswerOffSchema::at('card', 'missing object');
            }
            /** @var array<string, mixed> $raw */
            $repaired = self::shuffled($this->parser->card($card->kind, $raw), $card, $request->sceneId);
            self::assertFits($repaired, $card, $skeleton, $dialogue);
        } catch (ModelAnswerOffSchema $e) {
            return self::outcome(LessonCardRepairOutcome::OFF_SCHEMA, $card, $before, $raw, $findings, null, null, $reply, $e->getMessage());
        }

        $refused = $repaired instanceof VocabularyItem ? self::wordRefused($skeleton, $card, $repaired) : null;
        if ($refused !== null) {
            return self::outcome(LessonCardRepairOutcome::REFUSED, $card, $before, $raw, $findings, null, null, $reply, $refused);
        }

        [$newSkeleton, $newDialogue] = $card->replace($skeleton, $dialogue, $repaired);

        return self::outcome(LessonCardRepairOutcome::REPAIRED, $card, $before, $card->of($newSkeleton, $newDialogue), $findings, $newSkeleton, $newDialogue, $reply);
    }

    /**
     * A repaired card whose options the model ordered — a check, a listening question, the check of a whole exchange —
     * shuffled by the seed the day's dialogue was shuffled with.
     */
    private static function shuffled(SkeletonFrame|VocabularyItem|PartnerLine|DialogueExchange|ExchangeCheck|ListeningQuestion $card, LessonCard $at, string $seed): SkeletonFrame|VocabularyItem|PartnerLine|DialogueExchange|ExchangeCheck|ListeningQuestion
    {
        return match (true) {
            $card instanceof ExchangeCheck => OptionShuffle::check($card, "{$seed}:x{$at->number}:check"),
            $card instanceof ListeningQuestion => OptionShuffle::listening($card, "{$seed}:listening:".($at->number - 1)),
            $card instanceof DialogueExchange => $card->withExchange($card->exchange->withCheck(OptionShuffle::check($card->exchange->check, "{$seed}:x{$at->number}:check"))),
            default => $card,
        };
    }

    /**
     * What the schema cannot hold and the server does: a learner line of a repaired exchange stands on a frame of the skeleton
     * (a rescue on none); a repaired frame keeps, word for word, every filler the dialogue already says.
     */
    private static function assertFits(SkeletonFrame|VocabularyItem|PartnerLine|DialogueExchange|ExchangeCheck|ListeningQuestion $card, LessonCard $at, Skeleton $skeleton, ?Dialogue $dialogue): void
    {
        if ($card instanceof DialogueExchange) {
            $line = $card->exchange->learner();
            if ($line?->phraseId !== null && $skeleton->frame($line->phraseId) === null) {
                throw ModelAnswerOffSchema::at('card.messages', "«{$line->phraseId}» names no frame of the skeleton");
            }
        }
        if ($card instanceof SkeletonFrame && $dialogue !== null) {
            $old = $skeleton->frame($at->id);
            foreach ($dialogue->exchanges as $exchange) {
                $learner = $exchange->exchange->learner();
                if ($old === null || $learner === null || $learner->phraseId !== $at->id) {
                    continue;
                }
                $said = FrameText::line($old->phrase, $learner->textTarget)['filler'];
                if ($said !== null && $card->phrase->filler($said->target) === null) {
                    throw ModelAnswerOffSchema::at('card.slot', "the filler «{$said->target}» the dialogue says is gone");
                }
            }
        }
    }

    /** Why a word put in the place of another is not taken: the day already has it under another id. */
    private static function wordRefused(Skeleton $skeleton, LessonCard $card, VocabularyItem $word): ?string
    {
        foreach ($skeleton->vocabulary as $other) {
            if ($other->id !== $card->id && FrameText::identity($other->termTarget) === FrameText::identity($word->termTarget)) {
                return "«{$word->termTarget}» is already the day's word {$other->id}";
            }
        }

        return null;
    }

    /** @return array{before: array<string, mixed>|null, after: array<string, mixed>|null} */
    private static function neighbours(Dialogue $dialogue, int $step): array
    {
        return [
            'before' => $dialogue->exchange($step - 1)?->toArray(),
            'after' => $dialogue->exchange($step + 1)?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<LessonViolation>  $findings
     */
    private static function outcome(string $status, LessonCard $card, array $before, mixed $after, array $findings, ?Skeleton $skeleton, ?Dialogue $dialogue, ModelReply $reply, string $note = ''): LessonCardRepairOutcome
    {
        /** @var array<string, mixed>|null $shown */
        $shown = is_array($after) ? $after : null;

        return new LessonCardRepairOutcome(
            $status, $card->address, $card->kind, $before, $shown, self::rows($findings), $skeleton, $dialogue,
            $reply->costUsd, $reply->latencyMs, $reply->promptVersion, $note,
        );
    }

    /**
     * @param  list<LessonViolation>  $violations
     * @return list<array{code: string, address: string, detail: string}>
     */
    private static function rows(array $violations): array
    {
        return array_map(static fn (LessonViolation $v): array => $v->toArray(), $violations);
    }
}
