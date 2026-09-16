<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Request;

use App\Modules\Plan\Domain\ValueObject\CardResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The client's verdict on one card, and what the answer left behind (наряд SESSION-1a, D-30): what the recogniser
 * heard, when the hint was shown, the value put in the slot and the filler chosen, the mode the card was walked in,
 * and whether there was no microphone. Only these keys are kept — anything else the client sends is dropped, not
 * stored. An empty string arrives as null (the global `ConvertEmptyStringsToNull`), so every text is nullable: silence
 * is a response too.
 */
final class AnswerCardRequest extends FormRequest
{
    /** The modes a card can be walked in — the client's choice by level and «Без подсказок» (разд. 0). */
    public const MODES = ['chips', 'tiles', 'voice_hint', 'voice_blind'];

    /** @var list<string> the keys of `response` that are stored */
    private const RESPONSE_KEYS = ['heard', 'hinted_at', 'slot_value', 'filler_index', 'mode', 'no_mic'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::enum(CardResult::class)],
            'attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'response' => ['sometimes', 'nullable', 'array'],
            'response.heard' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'response.hinted_at' => ['sometimes', 'nullable', 'string', 'max:40'],
            'response.slot_value' => ['sometimes', 'nullable', 'string', 'max:200'],
            'response.filler_index' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9'],
            'response.mode' => ['sometimes', 'nullable', Rule::in(self::MODES)],
            'response.no_mic' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * The validated `response`, only its known keys, each as the type it names; null when the client sent none or
     * nothing that is kept.
     *
     * @return array<string, mixed>|null
     */
    public function cardResponse(): ?array
    {
        $given = $this->validated('response');
        if (! is_array($given)) {
            return null;
        }
        $out = [];
        foreach (self::RESPONSE_KEYS as $key) {
            if (! array_key_exists($key, $given)) {
                continue;
            }
            $value = $given[$key];
            $out[$key] = match (true) {
                ! is_scalar($value) => null,
                $key === 'filler_index' => (int) $value,
                $key === 'no_mic' => filter_var($value, FILTER_VALIDATE_BOOL),
                default => (string) $value,
            };
        }

        return $out === [] ? null : $out;
    }
}
