<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeVerdict;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SlotJudgeQuota;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\SpeechCoverage;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Shared\Domain\Service\LanguageName;
use DateTimeImmutable;
use Throwable;

/**
 * THE SLOT JUDGE (`slot_judge.v1`, наряд SESSION-1a, разд. 4): rules on one spoken attempt of a card judged by
 * meaning — the code first, the model only for what the code cannot say, and never a failure the vendor caused.
 *
 * `speak_answer` and `phrase_own_slot`, in this order:
 * 1. the frame's own words must be heard (coverage, the card's `coverage_min`) — else rejected by code, and neither
 *    the model nor the day's quota is touched: «say the frame» is not a question of meaning;
 * 2. a frame with no slot has nothing more to judge, and a value the lesson knows for the slot, heard as one run of
 *    words, is a value — both accepted by code, for free;
 * 3. otherwise the model, if today's quota has a call: it rules on the slot alone.
 *
 * `speak_retell` has no frame to cover: the model always rules, on whether the native retelling keeps the partner's
 * meaning.
 *
 * A model that is not asked (quota spent), does not answer, or answers off the shape leaves the attempt ACCEPTED on
 * the code's word — for an answer with the words heard beyond the frame as its slot — and counts
 * `judge.unavailable`, so the admin panel sees the judge's silence instead of the learner paying for it.
 *
 * The call is made OUTSIDE any transaction (D-27): nothing is locked while the learner waits on the model.
 */
final readonly class SlotJudge
{
    public const FRAME_NOT_SAID = 'Каркас не прозвучал — скажи его целиком';

    public const NOTHING_SAID = 'Я ничего не услышал — перескажи своими словами';

    public function __construct(
        private PlanModelPort $model,
        private SlotJudgeQuota $quota,
        private LearnerCalendar $calendar,
        private CheckCounters $counters,
        private LanguagePacks $packs,
        private PlanConfig $config,
        private SpeechCoverage $coverage = new SpeechCoverage,
    ) {}

    public function judge(Plan $plan, DayCard $card, string $heard, DateTimeImmutable $now): SlotJudgeVerdict
    {
        $payload = $card->payload();

        if ($card->kind() === CardKind::SpeakRetell) {
            $partner = self::line($payload['partner_line'] ?? null);
            // SILENCE IS NOT A RETELLING. The retell has no frame to cover, so without this floor the degraded pass —
            // the cap spent, the vendor silent — would accept an attempt with nothing in it, which the judge's own
            // prompt calls false. Nothing heard is the learner's own doing, never the vendor's, so the code says no.
            if (trim($heard) === '') {
                return SlotJudgeVerdict::byCode(false, null, self::NOTHING_SAID);
            }

            return $this->ask($plan, $now, new SlotJudgeRequest(
                task: SlotJudgeRequest::TASK_RETELL,
                targetLanguage: LanguageName::of($plan->targetLang()->value),
                nativeLanguage: LanguageName::of($plan->nativeLang()->value),
                level: $plan->level()->value,
                partnerLine: $partner['text_target'],
                partnerLineNative: $partner['text_native'],
                pattern: '',
                patternNative: '',
                slotHint: '',
                exampleValues: '',
                heard: $heard,
            ), fallbackSlot: null);
        }

        $target = $this->packs->for($plan->targetLang()->value);
        $frame = is_array($payload['frame'] ?? null) ? $payload['frame'] : [];
        $frameTarget = self::text($frame['frame_target'] ?? null);
        $part = FrameParts::part($frameTarget);

        if (! $this->coverage->covers($heard, $part, $this->minimum($payload['coverage_min'] ?? null, $part, $target), $target)) {
            return SlotJudgeVerdict::byCode(false, null, self::FRAME_NOT_SAID);
        }

        $slot = is_array($frame['slot'] ?? null) ? $frame['slot'] : null;
        if ($slot === null || ! FrameText::hasSlot($frameTarget)) {
            return SlotJudgeVerdict::byCode(true, null, null);
        }

        $values = [];
        foreach (is_array($slot['fillers'] ?? null) ? $slot['fillers'] : [] as $filler) {
            $value = is_array($filler) ? trim(self::text($filler['target'] ?? null)) : '';
            if ($value !== '') {
                $values[] = $value;
            }
        }
        foreach ($values as $value) {
            if ($this->coverage->containsSequence($heard, $value, $target)) {
                return SlotJudgeVerdict::byCode(true, $value, null);
            }
        }

        $partner = self::line($payload['partner_line'] ?? null);
        $beyond = $this->coverage->slotWords($heard, $frameTarget);

        return $this->ask($plan, $now, new SlotJudgeRequest(
            task: SlotJudgeRequest::TASK_ANSWER,
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level()->value,
            partnerLine: $partner['text_target'],
            partnerLineNative: $partner['text_native'],
            pattern: $frameTarget,
            patternNative: self::text($frame['frame_native'] ?? null),
            slotHint: self::text($slot['hint_native'] ?? null),
            exampleValues: implode('; ', $values),
            heard: $heard,
        ), fallbackSlot: $beyond === '' ? null : $beyond);
    }

    /** The model's ruling, or the code's when the model is not asked, is silent or answers off the shape. */
    private function ask(Plan $plan, DateTimeImmutable $now, SlotJudgeRequest $request, ?string $fallbackSlot): SlotJudgeVerdict
    {
        $user = $plan->userId();
        if (! $this->quota->take($user, $now, $this->calendar->timezoneFor($user), $this->config->slotJudgeDailyCap)) {
            return $this->unavailable($fallbackSlot);
        }

        try {
            $reply = $this->model->judgeSlot($request);
        } catch (Throwable) {
            return $this->unavailable($fallbackSlot);
        }

        $accepted = $reply->payload['accepted'] ?? null;
        $slotValue = $reply->payload['slot_value'] ?? null;
        $reason = $reply->payload['reason_native'] ?? null;
        if (! is_bool($accepted) || ($slotValue !== null && ! is_string($slotValue)) || ($reason !== null && ! is_string($reason))) {
            return $this->unavailable($fallbackSlot);
        }

        return SlotJudgeVerdict::byModel(
            $accepted,
            $request->task === SlotJudgeRequest::TASK_RETELL ? null : $slotValue,
            $reason,
            $reply,
        );
    }

    private function unavailable(?string $fallbackSlot): SlotJudgeVerdict
    {
        $this->counters->recordCodes($this->model->slotJudgePromptVersion(), [LessonCodes::JUDGE_UNAVAILABLE]);

        return SlotJudgeVerdict::unavailable($fallbackSlot);
    }

    /** The share the card was dealt with; a card without one asks what its frame would have been dealt with. */
    private function minimum(mixed $dealt, string $part, LanguagePack $target): float
    {
        if (is_int($dealt) || is_float($dealt)) {
            return (float) $dealt;
        }

        return $this->coverage->minFor($part, $target);
    }

    /** @return array{text_target: string, text_native: string} a line of the payload, empty texts for none */
    private static function line(mixed $line): array
    {
        return [
            'text_target' => is_array($line) ? self::text($line['text_target'] ?? null) : '',
            'text_native' => is_array($line) ? self::text($line['text_native'] ?? null) : '',
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
