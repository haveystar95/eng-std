<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\Service\LanguageName;
use Throwable;

/**
 * P2R — THE REPAIR OF ONE CARD (наряд GEN-2a). Asked two ways: by the lesson build for a card a fatal
 * finding holds ({@see LessonGateKeeper}, before the lesson is stored), and by the `plan:repair-card`
 * command for a stored lesson.
 *
 * The findings at the card (or only the named codes) go to the model with the card and the lesson as
 * context — the English detail of each finding, never another card's text. The model answers with the
 * card; the card is parsed to its shape, put into the answer, and the whole answer is validated again.
 * Nothing is written here: the build stores what passed its gate, the command writes only on `--apply`
 * ({@see \App\Modules\Plan\Application\Command\ReviseLessonHandler}).
 */
final readonly class LessonCardRepairer
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanModelPort $model,
        private LessonValidator $validator,
        private LessonParser $parser,
        private LearnerGender $gender,
        private PlanConfig $config,
    ) {}

    /**
     * A card of a stored lesson, by its address.
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

        $counts = $this->config->countsFor($plan->level());
        $request = new LessonRequest(
            topic: $scene->titleNative(),
            topicDescription: $scene->topicDescription(),
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level(),
            learnerGender: $this->gender->of($plan->userId()),
            vocabularyCount: $counts['vocabulary'],
            dialogueCount: $counts['dialogue'],
            targetLangCode: $plan->targetLang()->value,
            nativeLangCode: $plan->nativeLang()->value,
        );
        $context = self::contextOf($request);

        return $this->repairIn($answer, $card, $this->validator->run($answer, $context), $context, $request, $codes);
    }

    /**
     * A card of an answer in hand — the build's, before it is stored.
     *
     * @param  list<LessonViolation>  $found  the validator's findings over `$answer`
     * @param  list<string>  $codes  only these codes; all the card's findings when empty
     */
    public function repairIn(Lesson $answer, LessonCard $card, array $found, LessonValidationContext $context, LessonRequest $request, array $codes = []): LessonCardRepairOutcome
    {
        $before = $card->of($answer);
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
            lesson: $answer->toArray(),
            findings: $findings,
            frameIds: array_map(static fn (Phrase $p): string => $p->id, $answer->phrases),
            targetLanguage: $request->targetLanguage,
            nativeLanguage: $request->nativeLanguage,
            level: $request->level,
            learnerGender: $request->learnerGender,
        );
        try {
            $reply = $this->model->repairLessonCard($repairRequest);
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
            $repairedCard = $this->parser->card($card->kind, $raw);
        } catch (ModelAnswerOffSchema $e) {
            return new LessonCardRepairOutcome(
                LessonCardRepairOutcome::OFF_SCHEMA, $card->address, $card->kind, $before, is_array($raw) ? $raw : null,
                self::rows($atCard), [], null, [], count($found), $reply->costUsd, $reply->latencyMs, $reply->promptVersion, $e->getMessage(),
            );
        }

        $repaired = $card->replace($answer, $repairedCard);
        $after = $this->validator->run($repaired, $context);

        return new LessonCardRepairOutcome(
            status: LessonCardRepairOutcome::REPAIRED,
            address: $card->address,
            kind: $card->kind,
            before: $before,
            after: $card->of($repaired),
            findingsBefore: self::rows($atCard),
            findingsAfter: self::rows(array_values(array_filter($after, static fn (LessonViolation $v): bool => $card->covers($v)))),
            answer: $repaired,
            lessonFindings: self::rows($after),
            lessonFindingsBefore: count($found),
            costUsd: $reply->costUsd,
            latencyMs: $reply->latencyMs,
            promptVersion: $reply->promptVersion,
        );
    }

    /** What the validator reads of the lesson's inputs: the ordered counts and the language CODES (`ru`), not names. */
    public static function contextOf(LessonRequest $request): LessonValidationContext
    {
        return new LessonValidationContext(
            $request->vocabularyCount,
            $request->dialogueCount,
            $request->nativeLangCode !== '' ? $request->nativeLangCode : $request->nativeLanguage,
            $request->targetLangCode !== '' ? $request->targetLangCode : $request->targetLanguage,
            $request->learnerGender,
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
