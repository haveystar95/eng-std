<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\ExchangeCheck;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonCardContext;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Throwable;

/**
 * P2R — THE REPAIR OF ONE CARD (наряды GEN-2a, GEN-2b, GEN-3). Asked two ways: by the lesson build for a card a fatal
 * finding holds ({@see LessonGateKeeper}, before the lesson is stored), and by the `plan:repair-card` command for a
 * stored lesson.
 *
 * The findings at the card (or only the named codes) go to the model with the card and the part of the lesson the
 * card needs ({@see LessonCardContext}) — the English detail of each finding, never another card's text — a whole
 * exchange's NEIGHBOURS, and what the earlier days of the plan taught (EARLIER_DAYS: their frames and words). Card and
 * context are the answer as the server reads it ({@see LessonAssembly::said()}): the filler found in each line, the
 * marks of what the lines say, the key of the frame — the model's own `filler`, marks and key are shown to nobody.
 * The model answers with the card — an exchange may bring the frame its line stands on (`frame_update`), and the two
 * go in together or not at all; a learner line must stand on a frame of the lesson (the schema no longer names the
 * day's frames — its ids are the same for every call, so the vendor's prompt cache holds it). The card is parsed to its
 * shape, put into the answer, spoken in the plan's roles, and the whole answer is validated again. A repaired WORD is
 * checked again by the server before it counts: `used_in` true, not a word of an earlier day, not an abbreviation, not twice
 * in the day —
 * else the repair is refused like one off the card's shape. Nothing is written here: the build stores what passed its
 * gate, the command writes only on `--apply` ({@see \App\Modules\Plan\Application\Command\ReviseLessonHandler}).
 */
final readonly class LessonCardRepairer
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanModelPort $model,
        private LessonValidator $validator,
        private LessonParser $parser,
        private LessonRequests $requests,
        private LessonContexts $contexts,
    ) {}

    /**
     * A card of a stored lesson, by its address. The native seams the stored lesson was judged with stay with it
     * — every one but those of a frame the repair put in (the judge is asked once a day, not per repair).
     *
     * @param  list<string>  $codes  only these codes; all the card's findings when empty
     */
    public function repair(PlanSceneId $sceneId, string $address, array $codes = []): LessonCardRepairOutcome
    {
        $planId = $this->scenes->planIdOf($sceneId);
        $plan = $planId === null ? null : $this->plans->findById($planId);
        if ($plan === null) {
            throw SceneNotFound::withId($sceneId);
        }
        $scene = $plan->scene($sceneId);
        $answer = $scene->answer();
        $card = LessonCard::at($address);
        if ($answer === null || $card === null || $card->of($answer) === null) {
            return self::nothing(LessonCardRepairOutcome::NOT_A_CARD, $address, $card?->kind, 'no lesson, or no repairable card at this address');
        }

        $request = $this->requests->for($plan, $scene);
        $context = $this->contexts->of($request);

        $outcome = $this->repairIn($answer, $card, $this->validator->run($answer, $context), $context, $request, $codes);
        if ($outcome->status !== LessonCardRepairOutcome::REPAIRED) {
            return $outcome;
        }
        $changed = array_values(array_filter([$card->kind === LessonCard::FRAME ? $card->frameId : null, $outcome->frameUpdate['id'] ?? null], is_string(...)));
        $judged = array_values(array_filter(
            $scene->findings(),
            static fn (array $f): bool => in_array($f['code'], LessonCodes::JUDGED, true)
                && ! in_array(explode('.', $f['address'])[0], $changed, true),
        ));

        return $outcome->withLessonFindings($judged);
    }

    /**
     * A card of an answer in hand — the build's, before it is stored.
     *
     * @param  list<LessonViolation>  $found  the validator's findings over `$answer`
     * @param  list<string>  $codes  only these codes; all the card's findings when empty
     */
    public function repairIn(Lesson $answer, LessonCard $card, array $found, LessonValidationContext $context, LessonRequest $request, array $codes = []): LessonCardRepairOutcome
    {
        $read = LessonAssembly::said($answer, $context->target);
        $before = $card->of($read);
        if ($before === null) {
            return self::nothing(LessonCardRepairOutcome::NOT_A_CARD, $card->address, $card->kind, 'no repairable card at this address');
        }
        $atCard = array_values(array_filter(
            $found,
            static fn (LessonViolation $v): bool => $card->covers($v) && ($codes === [] || in_array($v->code, $codes, true)),
        ));
        if ($atCard === [] && $codes === []) {
            return self::nothing(LessonCardRepairOutcome::NOTHING_TO_REPAIR, $card->address, $card->kind, 'the validator finds nothing at this card', $before, count($found));
        }
        $findings = $atCard === []
            ? array_map(static fn (string $code): array => ['code' => $code, 'detail' => 'named by the session'], $codes)
            : array_map(static fn (LessonViolation $v): array => ['code' => $v->code, 'detail' => "{$v->address}: {$v->detail}"], $atCard);

        $repairRequest = new LessonCardRepairRequest(
            address: $card->address,
            kind: $card->kind,
            card: $before,
            context: LessonCardContext::of($read, $card),
            findings: $findings,
            neighbours: $card->kind === LessonCard::EXCHANGE ? LessonCardContext::neighbours($read, $card) : null,
            earlierDays: $request->earlierDays,
            targetLanguage: $request->targetLanguage,
            nativeLanguage: $request->nativeLanguage,
            level: $request->level,
            learnerGender: $request->learnerGender,
            dialogueCount: $request->dialogueCount,
            vocabularyCount: $request->vocabularyCount,
        );
        try {
            $reply = $this->model->repairLessonCard($repairRequest);
        } catch (PlanModelUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PlanModelUnavailable::because($e->getMessage());
        }

        $raw = $reply->payload['card'] ?? null;
        $rawFrame = $reply->payload['frame_update'] ?? null;
        $frameUpdate = null;
        try {
            if (! is_array($raw)) {
                throw ModelAnswerOffSchema::at('card', 'missing object');
            }
            /** @var array<string, mixed> $raw */
            $repairedCard = $this->parser->card($card->kind, $raw);
            $frameUpdate = $card->kind === LessonCard::EXCHANGE ? $this->parser->frameUpdate($rawFrame) : null;
            self::assertStandsOnAFrame($answer, $repairedCard);
            $repaired = $repairedCard instanceof Exchange
                ? $card->replaceExchange($answer, $repairedCard, $frameUpdate)
                : $card->replace($answer, $repairedCard);
            if ($repaired === null) {
                throw ModelAnswerOffSchema::at('frame_update', 'names no frame the repaired learner line stands on');
            }
        } catch (ModelAnswerOffSchema $e) {
            return self::offSchema($card, $before, $raw, $atCard, $found, $reply, $e->getMessage(), is_array($rawFrame) ? $rawFrame : null);
        }

        $repaired = $repaired->withRoles($request->roles);
        $after = $this->validator->run($repaired, $context);
        if ($card->kind === LessonCard::TERM && ($refused = self::wordRefused($repaired, $card, $after)) !== []) {
            return new LessonCardRepairOutcome(
                LessonCardRepairOutcome::REFUSED, $card->address, $card->kind, $before, $card->of($repaired),
                self::rows($atCard), [], null, [], count($found), $reply->costUsd, $reply->latencyMs, $reply->promptVersion,
                implode('; ', $refused),
            );
        }

        return new LessonCardRepairOutcome(
            status: LessonCardRepairOutcome::REPAIRED,
            address: $card->address,
            kind: $card->kind,
            before: $before,
            after: $card->of(LessonAssembly::said($repaired, $context->target)),
            findingsBefore: self::rows($atCard),
            findingsAfter: self::rows(array_values(array_filter($after, static fn (LessonViolation $v): bool => $card->covers($v)))),
            answer: $repaired,
            lessonFindings: self::rows($after),
            lessonFindingsBefore: count($found),
            costUsd: $reply->costUsd,
            latencyMs: $reply->latencyMs,
            promptVersion: $reply->promptVersion,
            frameUpdate: $frameUpdate?->toArray(),
        );
    }

    /**
     * A learner line of a repaired card stands on a frame of THIS lesson, or on none when it is a rescue — the schema holds
     * only the ids a day may have, so the server holds the rest.
     */
    private static function assertStandsOnAFrame(Lesson $answer, Phrase|Exchange|Message|ExchangeCheck|ListeningQuestion|VocabularyItem $card): void
    {
        $line = match (true) {
            $card instanceof Exchange => $card->learner(),
            $card instanceof Message => $card,
            default => null,
        };
        if ($line?->phraseId !== null && $answer->phrase($line->phraseId) === null) {
            throw ModelAnswerOffSchema::at('card.phrase_id', "«{$line->phraseId}» names no frame of the lesson");
        }
    }

    /**
     * Why the server refuses a repaired word (P2R v1.2, наряд GEN-3): what its own check of the word still finds — a
     * `used_in` that is not true, a word an earlier day taught, an abbreviation — and a word the day already lists under
     * another id.
     *
     * @param  list<LessonViolation>  $after  the validator's findings over the lesson with the repaired word
     * @return list<string>
     */
    private static function wordRefused(Lesson $repaired, LessonCard $card, array $after): array
    {
        $out = [];
        foreach ($after as $violation) {
            if ($violation->address === $card->address && in_array($violation->code, [LessonCodes::VOCAB_USED_IN_WRONG, LessonCodes::VOCAB_KNOWN_REPEAT, LessonCodes::VOCAB_ABBREVIATION], true)) {
                $out[] = "{$violation->code}: {$violation->detail}";
            }
        }
        $word = $repaired->vocabularyItem($card->frameId);
        foreach ($repaired->vocabulary as $other) {
            if ($word !== null && $other->id !== $word->id && FrameText::identity($other->termTarget) === FrameText::identity($word->termTarget)) {
                $out[] = "«{$word->termTarget}» is already the day's word {$other->id}";
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  list<LessonViolation>  $atCard
     * @param  list<LessonViolation>  $found
     * @param  array<string, mixed>|null  $frameUpdate
     */
    private static function offSchema(LessonCard $card, array $before, mixed $raw, array $atCard, array $found, ModelReply $reply, string $why, ?array $frameUpdate): LessonCardRepairOutcome
    {
        /** @var array<string, mixed>|null $after */
        $after = is_array($raw) ? $raw : null;

        return new LessonCardRepairOutcome(
            LessonCardRepairOutcome::OFF_SCHEMA, $card->address, $card->kind, $before, $after,
            self::rows($atCard), [], null, [], count($found), $reply->costUsd, $reply->latencyMs, $reply->promptVersion, $why, $frameUpdate,
        );
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private static function nothing(string $status, string $address, ?string $kind, string $note, ?array $before = null, int $findings = 0): LessonCardRepairOutcome
    {
        return new LessonCardRepairOutcome($status, $address, $kind, $before, null, [], [], null, [], $findings, '0.000000', 0, '', $note);
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
