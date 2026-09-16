<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE JUDGE'S RULING ON ONE ATTEMPT (наряд SESSION-1a, разд. 4), and who made it:
 *
 * - `code` — the program ruled without the model: the frame was not said (rejected), or a value the lesson knows
 *   was said in the slot (accepted), or the frame has no slot to judge (accepted);
 * - `model` — the model ruled on the slot or the retelling; its call's price and tokens travel with the ruling;
 * - `unavailable` — the model was asked for and did not rule (silent, off the shape, or the day's cap spent): the
 *   attempt is accepted on the code's word, because a learner who said the frame is not failed by a vendor.
 *
 * The call's fields are null unless the model ruled.
 */
final readonly class SlotJudgeVerdict
{
    public const BY_CODE = 'code';

    public const BY_MODEL = 'model';

    public const BY_UNAVAILABLE = 'unavailable';

    /** @param 'code'|'model'|'unavailable' $by */
    public function __construct(
        public bool $accepted,
        public ?string $slotValue,
        public ?string $reasonNative,
        public string $by,
        public ?string $model = null,
        public ?string $promptVersion = null,
        public ?string $costUsd = null,
        public ?int $latencyMs = null,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
    ) {}

    public static function byCode(bool $accepted, ?string $slotValue, ?string $reasonNative): self
    {
        return new self($accepted, $slotValue, $reasonNative, self::BY_CODE);
    }

    public static function unavailable(?string $slotValue): self
    {
        return new self(true, $slotValue, null, self::BY_UNAVAILABLE);
    }

    public static function byModel(bool $accepted, ?string $slotValue, ?string $reasonNative, ModelReply $reply): self
    {
        return new self(
            $accepted, $slotValue, $accepted ? null : $reasonNative, self::BY_MODEL,
            $reply->model, $reply->promptVersion, $reply->costUsd, $reply->latencyMs, $reply->tokensIn, $reply->tokensOut,
        );
    }

    /**
     * What the card keeps of the attempt (`day_cards.response`): what was heard, the slot's value, whether the frame
     * was shown before it, and the ruling with its call.
     *
     * @return array{heard: string, slot_value: string|null, hinted: bool, judge: array<string, mixed>}
     */
    public function response(string $heard, bool $hinted): array
    {
        return [
            'heard' => $heard,
            'slot_value' => $this->slotValue,
            'hinted' => $hinted,
            'judge' => [
                'accepted' => $this->accepted,
                'reason_native' => $this->reasonNative,
                'by' => $this->by,
                'model' => $this->model,
                'prompt_version' => $this->promptVersion,
                'cost_usd' => $this->costUsd,
                'latency_ms' => $this->latencyMs,
                'tokens_in' => $this->tokensIn,
                'tokens_out' => $this->tokensOut,
            ],
        ];
    }
}
