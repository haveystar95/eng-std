<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeVerdict;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SlotJudgeQuota;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Shared\Domain\Service\LanguageName;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;
use DateTimeImmutable;
use Throwable;

/**
 * THE SLOT JUDGE (`slot_judge.v3`, наряд SESSION-1a, разд. 4; наряд BACK-TAILS-1 §1.1; наряд CONV-2, пп. 7–8): rules on
 * one spoken attempt of a card judged by meaning — the code first, the model only for what the code cannot say, and
 * never a failure the vendor caused.
 *
 * Two kinds ask it ({@see \App\Modules\Plan\Domain\ValueObject\CardKind::asksJudge()}), and they are two different
 * questions (наряд CONV-2, п. 7):
 *
 * - «ОТВЕТЬ СВОИМИ СЛОВАМИ» (`speak_answer`) is judged BY MEANING AND BY KEYS: the learner answers the partner in their
 *   own words, and the frame is a hint of one way to say it, not a password. On the owner's gym day «Yes it is my first
 *   visit» to «Is this your first visit here?» was refused three times without the model being asked — «Каркас не
 *   прозвучал», because the frame was «This is ___.». Here the code accepts a value the lesson knows for the window,
 *   heard as one run of words, or — for a frame without a window — the frame's own words; everything else is the
 *   model's, in the mode `answer`;
 * - THE OWN-WORD ROUND of «Скажи целиком» (`phrase_other_slot`) is the frame said with a value of one's own, the frame
 *   on the screen: the frame's words must be heard (else «Каркас не прозвучал» by code, no model, no quota), a lesson's
 *   value is accepted by code, and the model judges the value alone, in the mode `own_value` — where a value that is
 *   not what the partner mentioned is still right (the towel of п. 8: «I will return the towel» was refused as «не
 *   назвал, что вернуть», because the partner had said «return the locker key»).
 *
 * In both, an attempt that says nothing but the frame's own words — «That works for me» to «which days?» — is refused
 * by code, free, with the window's hint in the learner's language: «Не сказал главного — в какие дни это подходит»
 * ({@see NativeStrings::judgeReason()}). The model wrote «Ты не сказал слово в пропуске» there, which names a thing
 * the learner cannot see.
 *
 * A model that is not asked (quota spent), does not answer, or answers off the shape leaves the attempt ACCEPTED on
 * the code's word — with the words heard beyond the frame as its slot — and counts `judge.unavailable`, so the admin
 * panel sees the judge's silence instead of the learner paying for it.
 *
 * The call is made OUTSIDE any transaction (D-27): nothing is locked while the learner waits on the model.
 */
final readonly class SlotJudge
{
    public const FRAME_NOT_SAID = 'Каркас не прозвучал — скажи его целиком';

    public function __construct(
        private PlanModelPort $model,
        private SlotJudgeQuota $quota,
        private LearnerCalendar $calendar,
        private CheckCounters $counters,
        private LanguagePacks $packs,
        private PlanConfig $config,
        private LearnerGender $learners,
        private SpeechMatch $speech = new SpeechMatch,
    ) {}

    public function judge(Plan $plan, DayCard $card, string $heard, DateTimeImmutable $now): SlotJudgeVerdict
    {
        $payload = $card->payload();
        $pack = $this->packs->for($plan->targetLang()->value);
        $target = $pack->speech();
        // «Не сказал(а) главного» ends as the learner's profile says (наряд FIX-3 §1).
        $strings = new NativeStrings($plan->nativeLang()->value, $this->learners->of($plan->userId()));
        $frame = is_array($payload['frame'] ?? null) ? $payload['frame'] : [];
        $frameTarget = self::text($frame['frame_target'] ?? null);
        $part = FrameParts::part($frameTarget);
        $answer = $card->kind() === CardKind::SpeakAnswer;
        $frameSaid = $this->speech->said($heard, $part, SpeechMode::Free, $target);

        if ($this->speech->words($heard, $target) === []) {
            return SlotJudgeVerdict::byCode(false, null, $answer ? $strings->judgeReason('nothing') : self::FRAME_NOT_SAID);
        }
        if (! $answer && ! $frameSaid) {
            return SlotJudgeVerdict::byCode(false, null, self::FRAME_NOT_SAID);
        }

        $slot = is_array($frame['slot'] ?? null) ? $frame['slot'] : null;
        $windowed = $slot !== null && FrameText::hasSlot($frameTarget);
        // No window: the frame said is the whole line — for «Скажи целиком» it always is by here; an answer in other
        // words goes to the model below.
        if (! $windowed && $frameSaid) {
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
            if ($this->speech->containsSequence($heard, $value, $target)) {
                return SlotJudgeVerdict::byCode(true, $value, null);
            }
        }

        $beyond = $this->speech->slotWords($heard, $part, $target);
        $hint = trim(self::text($slot['hint_native'] ?? null));
        if ($windowed && $hint !== '' && self::saysNothing($beyond, $pack)) {
            return SlotJudgeVerdict::byCode(false, null, $strings->judgeReason('main', $hint));
        }

        // The own-word round is the frame said with a value of one's own — not a reply to anybody: the partner's line is
        // not sent, because the model read «что нужно вернуть» off it as «the locker key the partner named» and refused
        // the owner's towel (наряд CONV-2, п. 8).
        $partner = $answer ? self::line($payload['partner_line'] ?? null) : self::line(null);

        return $this->ask($plan, $now, new SlotJudgeRequest(
            mode: $answer ? SlotJudgeRequest::MODE_ANSWER : SlotJudgeRequest::MODE_OWN_VALUE,
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level()->value,
            partnerLine: $partner['text_target'],
            partnerLineNative: $partner['text_native'],
            pattern: $frameTarget,
            patternNative: self::text($frame['frame_native'] ?? null),
            slotHint: $hint,
            exampleValues: implode('; ', $values),
            heard: $heard,
        ), fallbackSlot: $beyond === '' ? null : $beyond);
    }

    /**
     * Do the words heard beyond the frame say nothing — none at all, or only the words that carry no content of their
     * own («yes», «that», «please»: the pack's `function_words`)? Then the window is empty, whatever else was said.
     */
    private static function saysNothing(string $beyond, LanguagePack $pack): bool
    {
        foreach (preg_split('/\s+/u', trim($beyond), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (! $pack->has('function_words') || ! $pack->listed('function_words', $word)) {
                return false;
            }
        }

        return true;
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

        return SlotJudgeVerdict::byModel($accepted, $slotValue, $reason, $reply);
    }

    private function unavailable(?string $fallbackSlot): SlotJudgeVerdict
    {
        $this->counters->recordCodes($this->model->slotJudgePromptVersion(), [LessonCodes::JUDGE_UNAVAILABLE]);

        return SlotJudgeVerdict::unavailable($fallbackSlot);
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
