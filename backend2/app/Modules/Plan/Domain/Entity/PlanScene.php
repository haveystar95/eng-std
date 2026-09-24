<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneKind;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use DateTimeImmutable;

/**
 * One scene of the plan: the brief the plan builder wrote, and — once the lesson generator has
 * answered — the lesson with the cost and version it was written at, spoken in the roles the plan gives
 * (the learner's of the plan, the partner's of the scene — наряд GEN-3).
 *
 * The scene keeps the model's ANSWER (what is stored and what the validator judged) and serves the
 * lesson put together from it ({@see LessonAssembly}, seeded by the scene): the filler of every learner
 * line and the in-dialogue marks are what the server finds in the lines, the speaking keys come from the
 * frames — which words are content is the plan's target language's pack — and right answers stand at
 * shuffled places. Every reader deals from {@see lesson()}; only the store and a repair read {@see answer()}.
 *
 * A lesson written is not yet a ready day (DAY-UI-3): the scene is `illustrating` until its photos
 * are found, and only then `ready` — a day opens with its pictures on it. The partner's voice
 * gender is the cast of the scene's two voices ({@see partnerVoiceGender()}).
 */
final class PlanScene
{
    /** «По умолчанию собеседник женский, ученик мужской» (owner, DAY-UI-3). */
    public const DEFAULT_PARTNER_VOICE = VoiceGender::Female;

    private ?Lesson $lesson;

    /**
     * @param  list<string>  $goalsNative
     * @param  list<array{code: string, address: string, detail: string}>  $findings
     */
    private function __construct(
        private readonly PlanSceneId $id,
        private readonly PlanId $planId,
        private readonly int $order,
        private readonly SceneKind $kind,
        private readonly int $priority,
        private readonly string $titleNative,
        private readonly string $titleTarget,
        private readonly string $teachesNative,
        private readonly array $goalsNative,
        private readonly string $learnerRoleTarget,
        private readonly string $learnerRoleNative,
        private readonly string $partnerRoleTarget,
        private readonly string $partnerRoleNative,
        private readonly string $topicDescription,
        private readonly string $imagePrompt,
        private ?Image $image,
        private ?Lesson $answer,
        private LessonStatus $lessonStatus,
        private ?ModelCall $lessonCall,
        private array $findings,
        private ?string $failReason,
        private ?DateTimeImmutable $buildStartedAt,
        private ?DateTimeImmutable $generatedAt,
        private ?VoiceGender $partnerVoiceGender,
        ?LanguagePack $targetPack,
        private ?DateTimeImmutable $builtAt = null,
    ) {
        $this->lesson = $answer === null || $targetPack === null ? null : LessonAssembly::serve($answer, $id->value, $targetPack);
    }

    /** A scene of the plan's brief: no lesson yet, nothing to serve — the lesson comes with its language ({@see acceptLesson()}). */
    public static function fromBrief(PlanSceneId $id, PlanId $planId, SceneBrief $brief): self
    {
        return new self(
            $id, $planId, $brief->order, $brief->kind, $brief->priority, $brief->titleNative, $brief->titleTarget,
            $brief->teachesNative, $brief->goalsNative, $brief->learnerRoleTarget, $brief->learnerRoleNative,
            $brief->partnerRoleTarget, $brief->partnerRoleNative, $brief->topicDescription, $brief->imagePrompt,
            null, null, LessonStatus::Pending, null, [], null, null, null, null, null,
        );
    }

    /**
     * @param  list<string>  $goalsNative
     * @param  list<array{code: string, address: string, detail: string}>  $findings
     * @param  LanguagePack  $targetPack  the plan's target language — the served lesson's keys read its content words
     */
    public static function reconstitute(
        PlanSceneId $id,
        PlanId $planId,
        int $order,
        SceneKind $kind,
        int $priority,
        string $titleNative,
        string $titleTarget,
        string $teachesNative,
        array $goalsNative,
        string $learnerRoleTarget,
        string $learnerRoleNative,
        string $partnerRoleTarget,
        string $partnerRoleNative,
        string $topicDescription,
        string $imagePrompt,
        ?Image $image,
        ?Lesson $answer,
        LessonStatus $lessonStatus,
        ?ModelCall $lessonCall,
        array $findings,
        ?string $failReason,
        ?DateTimeImmutable $buildStartedAt,
        ?DateTimeImmutable $generatedAt,
        LanguagePack $targetPack,
        ?VoiceGender $partnerVoiceGender = null,
        ?DateTimeImmutable $builtAt = null,
    ): self {
        return new self(
            $id, $planId, $order, $kind, $priority, $titleNative, $titleTarget, $teachesNative, $goalsNative,
            $learnerRoleTarget, $learnerRoleNative, $partnerRoleTarget, $partnerRoleNative, $topicDescription,
            $imagePrompt, $image, $answer, $lessonStatus, $lessonCall, $findings, $failReason, $buildStartedAt, $generatedAt,
            $partnerVoiceGender, $targetPack, $builtAt,
        );
    }

    /** The lesson call is claimed: a second dispatch of the same scene finds it building and stops. */
    public function startLessonBuild(DateTimeImmutable $now): void
    {
        $this->lessonStatus = LessonStatus::Building;
        $this->buildStartedAt = $now;
        $this->failReason = null;
    }

    /**
     * The lesson is written: the scene waits for its photos (`illustrating`) and knows its voices — the
     * partner's gender the lesson imagined for the role, the default when it said none.
     *
     * @param  LanguagePack  $targetPack  the plan's target language, which the served lesson's keys are read in
     * @param list<array{code: string, address: string, detail: string}> $findings
     */
    public function acceptLesson(Lesson $answer, LanguagePack $targetPack, ModelCall $call, array $findings, DateTimeImmutable $now): void
    {
        $this->answer = $answer;
        $this->lesson = LessonAssembly::serve($answer, $this->id->value, $targetPack);
        $this->lessonStatus = LessonStatus::Illustrating;
        $this->lessonCall = $call;
        $this->findings = $findings;
        $this->failReason = null;
        $this->generatedAt = $now;
        $this->partnerVoiceGender = $answer->roleGender ?? self::DEFAULT_PARTNER_VOICE;
    }

    /**
     * One card of the answer was repaired (P2R): the repaired answer replaces the old one, the findings
     * are the validator's over it, and what the repair cost is added to the lesson's cost.
     *
     * @param  LanguagePack  $targetPack  the plan's target language, which the served lesson's keys are read in
     * @param list<array{code: string, address: string, detail: string}> $findings
     */
    public function reviseLesson(Lesson $answer, LanguagePack $targetPack, array $findings, string $repairCostUsd): void
    {
        $this->answer = $answer;
        $this->lesson = LessonAssembly::serve($answer, $this->id->value, $targetPack);
        $this->findings = $findings;
        $this->lessonCall = $this->lessonCall?->plusCost($repairCostUsd);
    }

    /**
     * The photos are in (or every search came back empty and the slots got their tones): the day is ready — and the build
     * is over, at `$now` (наряд FIX-4 §6: `built_at`, the END of the build; `generated_at` is its beginning).
     */
    public function finishIllustration(DateTimeImmutable $now): void
    {
        if ($this->lessonStatus === LessonStatus::Illustrating) {
            $this->lessonStatus = LessonStatus::Ready;
            $this->builtAt = $now;
        }
    }

    /** @param list<array{code: string, address: string, detail: string}> $findings */
    public function failLesson(string $reason, ?ModelCall $call, array $findings): void
    {
        $this->lessonStatus = LessonStatus::Failed;
        $this->failReason = $reason;
        $this->lessonCall = $call;
        $this->findings = $findings;
    }

    /** «Не собрался — попробовать ещё раз»: back to pending so a dispatch is legal again. */
    public function resetLesson(): void
    {
        $this->lessonStatus = LessonStatus::Pending;
        $this->failReason = null;
        $this->buildStartedAt = null;
    }

    public function attachImage(Image $image): void
    {
        $this->image ??= $image;
    }

    /**
     * The lesson is still to be asked for — `pending`. A FAILED lesson is not: it waits for the learner's retry
     * ({@see resetLesson()}), and nothing buys it again on its own (наряд GEN-3) — a call lost to a timeout may have been
     * billed, and a day that opens or closes must not pay for it twice.
     */
    public function needsLesson(): bool
    {
        return $this->lessonStatus === LessonStatus::Pending;
    }

    /** The lesson is not written yet — asked for, being written, or waiting for its photos (наряд GEN-3 §11). */
    public function isAwaitingLesson(): bool
    {
        return in_array($this->lessonStatus, [LessonStatus::Pending, LessonStatus::Building, LessonStatus::Illustrating], true);
    }

    public function isReady(): bool
    {
        return $this->lessonStatus === LessonStatus::Ready && $this->lesson !== null;
    }

    /** The lesson is written — ready, or still waiting for its photos. */
    public function hasLesson(): bool
    {
        return ($this->lessonStatus === LessonStatus::Ready || $this->lessonStatus === LessonStatus::Illustrating)
            && $this->lesson !== null;
    }

    public function isIllustrating(): bool
    {
        return $this->lessonStatus === LessonStatus::Illustrating;
    }

    /**
     * Whose voice the partner's lines are read with; the learner's lines, phrases and words take the
     * other. Stored when the lesson was written (or cast by the voice queue for a scene written before
     * voices had genders); a scene with none speaks with the default cast.
     */
    public function partnerVoiceGender(): ?VoiceGender
    {
        return $this->partnerVoiceGender;
    }

    /** A build that started and never finished within `$staleAfterSeconds` counts as dead. */
    public function isBuildStale(DateTimeImmutable $now, int $staleAfterSeconds): bool
    {
        return $this->lessonStatus === LessonStatus::Building
            && $this->buildStartedAt !== null
            && $now->getTimestamp() - $this->buildStartedAt->getTimestamp() > $staleAfterSeconds;
    }

    public function id(): PlanSceneId
    {
        return $this->id;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function kind(): SceneKind
    {
        return $this->kind;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isCore(): bool
    {
        return $this->priority === 1;
    }

    public function titleNative(): string
    {
        return $this->titleNative;
    }

    public function titleTarget(): string
    {
        return $this->titleTarget;
    }

    public function teachesNative(): string
    {
        return $this->teachesNative;
    }

    /** @return list<string> */
    public function goalsNative(): array
    {
        return $this->goalsNative;
    }

    public function learnerRoleTarget(): string
    {
        return $this->learnerRoleTarget;
    }

    public function learnerRoleNative(): string
    {
        return $this->learnerRoleNative;
    }

    public function partnerRoleTarget(): string
    {
        return $this->partnerRoleTarget;
    }

    public function partnerRoleNative(): string
    {
        return $this->partnerRoleNative;
    }

    public function topicDescription(): string
    {
        return $this->topicDescription;
    }

    public function imagePrompt(): string
    {
        return $this->imagePrompt;
    }

    public function image(): ?Image
    {
        return $this->image;
    }

    /** The lesson every reader deals from — the answer put together by the server. */
    public function lesson(): ?Lesson
    {
        return $this->lesson;
    }

    /** The model's answer as written — what is stored, validated and repaired. */
    public function answer(): ?Lesson
    {
        return $this->answer;
    }

    public function lessonStatus(): LessonStatus
    {
        return $this->lessonStatus;
    }

    public function lessonCall(): ?ModelCall
    {
        return $this->lessonCall;
    }

    /** @return list<array{code: string, address: string, detail: string}> */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * The fillers whose native sentence the seam judge said does not read — the addresses (`p3.f2`) of the lesson's
     * `filler.native_seam` findings (SESSION-1e): what the day's cards do not show.
     *
     * @return list<string>
     */
    public function unreadableFillers(): array
    {
        $out = [];
        foreach ($this->findings as $finding) {
            if ($finding['code'] === LessonCodes::FILLER_NATIVE_SEAM && ! in_array($finding['address'], $out, true)) {
                $out[] = $finding['address'];
            }
        }

        return $out;
    }

    public function failReason(): ?string
    {
        return $this->failReason;
    }

    public function buildStartedAt(): ?DateTimeImmutable
    {
        return $this->buildStartedAt;
    }

    public function generatedAt(): ?DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /** When the build ended — the scene went ready, its photos in (наряд FIX-4 §6); null before that. */
    public function builtAt(): ?DateTimeImmutable
    {
        return $this->builtAt;
    }
}
