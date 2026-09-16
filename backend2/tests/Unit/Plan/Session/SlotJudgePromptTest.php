<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeVerdict;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;

/**
 * THE SLOT JUDGE'S PROMPT (`slot_judge.v1`, наряд SESSION-1a, разд. 4): the file accepted byte for byte from the order,
 * the inputs one line each in the prompt's order with their values as they are, and the strict output schema.
 */

/** The sha256 of the order's block «SLOT JUDGE — v1 … the last }.» plus one newline, computed from `naryad.md`. */
const SLOT_JUDGE_V1_SHA256 = 'b8af3273899f2c78024622cac810e428be17a8d84c75fae172e0badcf3ed6f74';

it('keeps the prompt file byte for byte as the order wrote it, and sends it as the system side unchanged', function () {
    $path = dirname(__DIR__, 4).'/app/Modules/Plan/Infrastructure/Prompt/'.PlanPromptFiles::SLOT_JUDGE_FILE;
    $raw = (string) file_get_contents($path);
    $prompts = new PlanPromptFiles;

    expect(hash('sha256', $raw))->toBe(SLOT_JUDGE_V1_SHA256)
        ->and($raw)->toStartWith("SLOT JUDGE — v1\n")
        ->and($raw)->toEndWith("the first character { and the last }.\n")
        ->and(str_ends_with($raw, "\n\n"))->toBeFalse()
        ->and(hash('sha256', $prompts->slotJudgeSystem()))->toBe(SLOT_JUDGE_V1_SHA256)
        ->and($prompts->slotJudgeVersion())->toBe('slot_judge.v1');
});

it('writes the inputs one line each, in the prompt\'s order, with the values as they came', function () {
    $prompts = new PlanPromptFiles;

    $answer = $prompts->slotJudgeUser(new SlotJudgeRequest(
        task: SlotJudgeRequest::TASK_ANSWER,
        targetLanguage: 'English',
        nativeLanguage: 'Russian',
        level: 'intermediate',
        partnerLine: 'Where does it hurt: his upper back or his lower back?',
        partnerLineNative: 'Где болит: вверху спины или в пояснице?',
        pattern: 'It hurts in his ___.',
        patternNative: 'У него болит ___.',
        slotHint: 'где болит',
        exampleValues: 'lower back; neck; shoulder',
        heard: 'it  hurts in his   left knee ',
    ));

    expect($answer)->toBe(implode("\n", [
        'TASK: answer',
        'TARGET_LANGUAGE: English',
        'NATIVE_LANGUAGE: Russian',
        'LEVEL: intermediate',
        'PARTNER_LINE: Where does it hurt: his upper back or his lower back?',
        'PARTNER_LINE_NATIVE: Где болит: вверху спины или в пояснице?',
        'PATTERN: It hurts in his ___.',
        'PATTERN_NATIVE: У него болит ___.',
        'SLOT_HINT: где болит',
        'EXAMPLE_VALUES: lower back; neck; shoulder',
        // Not collapsed, not trimmed: the recognition noise is the model's to forgive.
        'HEARD: it  hurts in his   left knee ',
    ]));

    $retell = $prompts->slotJudgeUser(new SlotJudgeRequest(
        SlotJudgeRequest::TASK_RETELL, 'English', 'Russian', 'beginner',
        'He should rest.', 'Ему нужен покой.', '', '', '', '', 'ему надо отдыхать',
    ));

    expect(explode("\n", $retell))->toBe([
        'TASK: retell',
        'TARGET_LANGUAGE: English',
        'NATIVE_LANGUAGE: Russian',
        'LEVEL: beginner',
        'PARTNER_LINE: He should rest.',
        'PARTNER_LINE_NATIVE: Ему нужен покой.',
        'PATTERN: ',
        'PATTERN_NATIVE: ',
        'SLOT_HINT: ',
        'EXAMPLE_VALUES: ',
        'HEARD: ему надо отдыхать',
    ]);
});

it('asks for the strict output — accepted, the slot\'s value and the reason, nothing else', function () {
    expect(PlanSchemas::slotJudge())->toBe([
        'type' => 'object',
        'properties' => [
            'accepted' => ['type' => 'boolean'],
            'slot_value' => ['type' => ['string', 'null']],
            'reason_native' => ['type' => ['string', 'null']],
        ],
        'required' => ['accepted', 'slot_value', 'reason_native'],
        'additionalProperties' => false,
    ]);
});

it('keeps on the card what was heard and the ruling, the call\'s fields only when the model ruled', function () {
    $reply = new ModelReply(['accepted' => false], 'slot_judge.v1', 'gpt-5.4-mini', 410, 22, '0.000180', 930);

    expect(SlotJudgeVerdict::byModel(false, 'banana', 'Ты не сказал, где болит.', $reply)->response('It hurts in his banana', true))->toBe([
        'heard' => 'It hurts in his banana',
        'slot_value' => 'banana',
        'hinted' => true,
        'judge' => [
            'accepted' => false,
            'reason_native' => 'Ты не сказал, где болит.',
            'by' => 'model',
            'model' => 'gpt-5.4-mini',
            'prompt_version' => 'slot_judge.v1',
            'cost_usd' => '0.000180',
            'latency_ms' => 930,
            'tokens_in' => 410,
            'tokens_out' => 22,
        ],
    ])
        // An accepted ruling has no reason, whatever the model wrote.
        ->and(SlotJudgeVerdict::byModel(true, 'neck', 'лишнее', $reply)->reasonNative)->toBeNull()
        ->and(SlotJudgeVerdict::unavailable('left knee')->response('It hurts in his left knee', false)['judge'])->toBe([
            'accepted' => true,
            'reason_native' => null,
            'by' => 'unavailable',
            'model' => null,
            'prompt_version' => null,
            'cost_usd' => null,
            'latency_ms' => null,
            'tokens_in' => null,
            'tokens_out' => null,
        ]);
});
