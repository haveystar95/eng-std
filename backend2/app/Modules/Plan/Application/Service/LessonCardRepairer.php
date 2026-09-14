<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\Service\LanguageName;

/**
 * P2R — THE REPAIR OF ONE CARD (наряд GEN-2a). Asked only by an explicit command, never by the build.
 *
 * The card is found by its address in the scene's stored ANSWER; the validator runs over the answer and
 * the findings at that card (or only the named codes) go to the model with the card and the lesson as
 * context — the English detail of each finding, never another card's text. The model answers with the
 * card; the card is parsed to its shape, put into the answer, and the whole answer is validated again.
 * Nothing is written here: whether the repaired answer replaces the stored one is {@see
 * \App\Modules\Plan\Application\Command\ReviseLessonHandler}'s, on the command's `--apply`.
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
    ) {}

    /** @param list<string> $codes only these codes; all the card's findings when empty */
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
        $before = $answer === null || $card === null ? null : $card->of($answer);
        if ($answer === null || $card === null || $before === null) {
            return self::nothing(LessonCardRepairOutcome::NOT_A_CARD, $address, $card?->kind, 'no lesson, or no repairable card at this address');
        }

        $gender = $this->gender->of($plan->userId());
        $context = new LessonValidationContext(
            count($answer->vocabulary), count($answer->exchanges),
            $plan->nativeLang()->value, $plan->targetLang()->value, $gender,
        );
        $all = $this->validator->run($answer, $context);
        $atCard = array_values(array_filter(
            $all,
            static fn (LessonViolation $v): bool => $card->covers($v) && ($codes === [] || in_array($v->code, $codes, true)),
        ));
        if ($atCard === [] && $codes === []) {
            return self::nothing(LessonCardRepairOutcome::NOTHING_TO_REPAIR, $address, $card->kind, 'the validator finds nothing at this card', $before, count($all));
        }
        $findings = $atCard === []
            ? array_map(static fn (string $code): array => ['code' => $code, 'detail' => 'named by the session'], $codes)
            : array_map(static fn (LessonViolation $v): array => ['code' => $v->code, 'detail' => "{$v->address}: {$v->detail}"], $atCard);

        $reply = $this->model->repairLessonCard(new LessonCardRepairRequest(
            address: $card->address,
            kind: $card->kind,
            card: $before,
            lesson: $answer->toArray(),
            findings: $findings,
            frameIds: array_map(static fn (Phrase $p): string => $p->id, $answer->phrases),
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level(),
            learnerGender: $gender,
        ));

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
                self::rows($atCard), [], null, [], count($all), $reply->costUsd, $reply->latencyMs, $reply->promptVersion, $e->getMessage(),
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
            lessonFindingsBefore: count($all),
            costUsd: $reply->costUsd,
            latencyMs: $reply->latencyMs,
            promptVersion: $reply->promptVersion,
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
