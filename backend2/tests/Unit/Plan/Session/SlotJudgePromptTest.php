<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeVerdict;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;

/**
 * THE SLOT JUDGE'S PROMPT (`slot_judge.v3`, наряд SESSION-1a, разд. 4; наряд BACK-TAILS-1 §1.1; наряд CONV-2, пп. 7–8):
 * the frozen file, the inputs one line each in the prompt's order with their values as they are, and the strict output
 * schema.
 */

/** The sha256 of `slot_judge.v3.md`: the file is frozen, and a change to it is a new version, never a new hash here. */
const SLOT_JUDGE_V3_SHA256 = '035411394c79d4e6f3d814f96933eecf2f7b56323e32f2cd26525cff26d7b6e4';

// Canon: «правится файл → меняется имя → меняется версия» (docs/plan-v2.md §0). Наряд CONV-2 gave the judge two modes —
// an answer judged by its meaning (п. 7) and an own value that need not be the partner's (п. 8) — and a reason in the
// words of the window's hint, so v2 became v3. Catches an edit made to a frozen prompt in place, which would leave
// `plan_check_counters` and every stored verdict naming a version that no longer says what it said, and a v3 that lost
// one of the three rules it was written for.
it('keeps the prompt file frozen under its own version, and sends it as the system side unchanged', function () {
    $path = dirname(__DIR__, 4).'/app/Modules/Plan/Infrastructure/Prompt/'.PlanPromptFiles::SLOT_JUDGE_FILE;
    $raw = (string) file_get_contents($path);
    $prompts = new PlanPromptFiles;

    expect(hash('sha256', $raw))->toBe(SLOT_JUDGE_V3_SHA256)
        ->and($raw)->toStartWith("SLOT JUDGE — v3\n")
        ->and($raw)->toEndWith("the first character { and the last }.\n")
        ->and(str_ends_with($raw, "\n\n"))->toBeFalse()
        ->and($raw)->not->toContain('TASK')
        // п. 7: an answer in other words is right — the owner's «Yes, it is my first visit».
        ->and($raw)->toContain('MODE answer')->toContain('PATTERN is one way to say it, not a requirement')
        ->and($raw)->toContain('"Yes, it is my first visit" is a right answer to "Is this your first visit here?"')
        // п. 8: the own value need not be what the partner mentioned — the towel.
        ->and($raw)->toContain('MODE own_value')->toContain('SLOT_HINT names the KIND of value, not the one right answer')
        ->and($raw)->toContain('It does NOT have to be one of EXAMPLE_VALUES or anything the scene mentioned')
        // The reason names what is missing, never the gap the learner cannot see.
        ->and($raw)->toContain('in the words of SLOT_HINT')->toContain('"пропуск"')
        ->and(hash('sha256', $prompts->slotJudgeSystem()))->toBe(SLOT_JUDGE_V3_SHA256)
        ->and($prompts->slotJudgeVersion())->toBe('slot_judge.v3');
});

it('writes the inputs one line each, in the prompt\'s order, with the values as they came', function () {
    $prompts = new PlanPromptFiles;

    $answer = $prompts->slotJudgeUser(new SlotJudgeRequest(
        mode: SlotJudgeRequest::MODE_OWN_VALUE,
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
        'MODE: own_value',
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
    $reply = new ModelReply(['accepted' => false], 'slot_judge.v3', 'gpt-5.4-mini', 410, 22, '0.000180', 930);

    expect(SlotJudgeVerdict::byModel(false, 'banana', 'Ты не сказал, где болит.', $reply)->response('It hurts in his banana', true))->toBe([
        'heard' => 'It hurts in his banana',
        'slot_value' => 'banana',
        'hinted' => true,
        'judge' => [
            'accepted' => false,
            'reason_native' => 'Ты не сказал, где болит.',
            'by' => 'model',
            'model' => 'gpt-5.4-mini',
            'prompt_version' => 'slot_judge.v3',
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
