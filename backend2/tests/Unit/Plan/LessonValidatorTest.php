<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE LESSON VALIDATOR, CODE BY CODE (`lesson_day.v4.4`, docs/plan-v2.md §4).
 *
 * The clean fixture lesson breaks nothing; every row below breaks ONE rule of the prompt in it and names
 * the code that must count the breach — the defect each code exists to catch, run against the code. A
 * rule the validator stops reading fails its own row.
 */

/** @return array<string, mixed> */
function lvPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8));
}

/** @return list<LessonViolation> */
function lvRun(array $payload, ?VoiceGender $gender = null): array
{
    return (new LessonValidator)->run((new LessonParser)->parse($payload), new LessonValidationContext(8, 8, 'ru', 'en', $gender));
}

/** @return list<string> */
function lvCodes(array $payload, ?VoiceGender $gender = null): array
{
    return array_values(array_unique(array_map(static fn (LessonViolation $v): string => $v->code, lvRun($payload, $gender))));
}

it('finds nothing in a lesson that keeps every rule', function () {
    expect(lvRun(lvPayload()))->toBe([]);
});

/** @return array<string, array{0: string, 1: Closure}> a description → the code and the break */
function lvBreaks(): array
{
    return [
    'the ordered number of exchanges' => [LessonCodes::DIALOGUE_COUNT, static function (array $p): array {
        array_pop($p['dialogue']);

        return $p;
    }],
    'the ordered number of vocabulary items' => [LessonCodes::VOCAB_COUNT, static function (array $p): array {
        array_pop($p['vocabulary']);

        return $p;
    }],
    'an answer exchange said to be opened by the learner' => [LessonCodes::EXCHANGE_SHAPE, static function (array $p): array {
        $p['dialogue'][0]['initiator'] = 'B';

        return $p;
    }],
    'the closing message asks' => [LessonCodes::EXCHANGE_SECOND_QUESTION, static function (array $p): array {
        $p['dialogue'][3]['messages'][1]['text_target'] = 'Should he have a fever?';

        return $p;
    }],
    'a check with two options' => [LessonCodes::CHECK_SHAPE, static function (array $p): array {
        array_pop($p['dialogue'][1]['check']['options']);

        return $p;
    }],
    'a listening question with two options' => [LessonCodes::LISTENING_SHAPE, static function (array $p): array {
        array_pop($p['listening']['questions'][2]['options_native']);

        return $p;
    }],
    'a reading in Latin letters' => [LessonCodes::PRONUNCIATION_SCRIPT, static function (array $p): array {
        $p['vocabulary'][1]['pronunciation_native'] = 'sharp';

        return $p;
    }],
    'more frames than answer and ask exchanges' => [LessonCodes::FRAME_COUNT, static function (array $p): array {
        foreach ([7, 8] as $n) {
            $p['phrases'][] = ['id' => "p{$n}", 'kind' => 'answer', 'frame_target' => 'He feels tired.', 'frame_native' => 'Он устал.', 'pronunciation_native' => 'хи филз тайрд', 'slot' => null];
        }

        return $p;
    }],
    'a frame no line says' => [LessonCodes::FRAME_UNUSED, static function (array $p): array {
        $p['phrases'][] = ['id' => 'p7', 'kind' => 'answer', 'frame_target' => 'He feels tired.', 'frame_native' => 'Он устал.', 'pronunciation_native' => 'хи филз тайрд', 'slot' => null];

        return $p;
    }],
    'eight words outside the slot' => [LessonCodes::FRAME_TOO_LONG, static function (array $p): array {
        $p['phrases'][1]['frame_target'] = 'It really started to hurt him quite badly ___.';

        return $p;
    }],
    'half the frames without a slot' => [LessonCodes::FRAME_NO_SLOT_SHARE, static function (array $p): array {
        foreach ([0, 1] as $i) {
            $p['phrases'][$i]['frame_target'] = str_replace('___', 'now', $p['phrases'][$i]['frame_target']);
            $p['phrases'][$i]['slot'] = null;
        }

        return $p;
    }],
    'alternatives written into the native frame' => [LessonCodes::FRAME_NATIVE_ALTERNATIVES, static function (array $p): array {
        $p['phrases'][4]['frame_native'] = 'Он будет отдыхать в/на ___.';

        return $p;
    }],
    'the native frame ends with another mark' => [LessonCodes::FRAME_NATIVE_PUNCT, static function (array $p): array {
        $p['phrases'][0]['frame_native'] = 'У него болит ___?';

        return $p;
    }],
    'one filler in a slot' => [LessonCodes::FILLER_COUNT, static function (array $p): array {
        $p['phrases'][2]['slot']['fillers'] = [$p['phrases'][2]['slot']['fillers'][0]];

        return $p;
    }],
    'a filler that doubles a word of the frame' => [LessonCodes::FILLER_UNGRAMMATICAL, static function (array $p): array {
        $p['phrases'][0]['slot']['fillers'][1]['target'] = 'his neck';

        return $p;
    }],
    'a filler marked in_dialogue that no line says' => [LessonCodes::FILLER_ONE_IN_DIALOGUE, static function (array $p): array {
        $p['phrases'][0]['slot']['fillers'][2]['in_dialogue'] = true;

        return $p;
    }],
    'a line that is not its frame with its filler' => [LessonCodes::LINE_NE_FRAME, static function (array $p): array {
        $p['dialogue'][1]['messages'][1]['text_target'] = 'It began three days ago.';

        return $p;
    }],
    'eleven words without the glue' => [LessonCodes::LINE_TOO_LONG, static function (array $p): array {
        $long = 'three days ago on Monday after school at home';
        $p['phrases'][1]['slot']['fillers'][0]['target'] = $long;
        $p['dialogue'][1]['messages'][1]['filler'] = $long;
        $p['dialogue'][1]['messages'][1]['text_target'] = "It started {$long}.";

        return $p;
    }],
    'an answer line without a frame' => [LessonCodes::LINE_NO_FRAME, static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['phrase_id'] = null;

        return $p;
    }],
    'a key that is not in the line' => [LessonCodes::KEY_NOT_IN_LINE, static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['speaking_key'] = 'hurts badly';

        return $p;
    }],
    'a key made of the filler' => [LessonCodes::KEY_CONTAINS_FILLER, static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['speaking_key'] = 'his lower back';

        return $p;
    }],
    'a key of function words' => [LessonCodes::KEY_NO_CONTENT_WORD, static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['speaking_key'] = 'in his';

        return $p;
    }],
    'a key of five words' => [LessonCodes::KEY_TOO_LONG, static function (array $p): array {
        $p['dialogue'][2]['messages'][1]['speaking_key'] = 'pain is sharp when he';

        return $p;
    }],
    'a variant longer than the line' => [LessonCodes::VARIANT_LONGER, static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['simplified_variants'] = ['It hurts in his lower back very much today.'];

        return $p;
    }],
    'one ask exchange' => [LessonCodes::KIND_ASK_COUNT, static function (array $p): array {
        $p['dialogue'][6]['kind'] = 'answer';
        $p['dialogue'][6]['initiator'] = 'A';
        $p['dialogue'][6]['messages'] = array_reverse($p['dialogue'][6]['messages']);

        return $p;
    }],
    'two rescues' => [LessonCodes::KIND_RESCUE_COUNT, static function (array $p): array {
        $p['dialogue'][7]['kind'] = 'rescue';

        return $p;
    }],
    'a rescue opened by the partner' => [LessonCodes::RESCUE_NOT_FIRST, static function (array $p): array {
        $p['dialogue'][5]['messages'] = array_reverse($p['dialogue'][5]['messages']);

        return $p;
    }],
    'a rescue that says something new' => [LessonCodes::RESCUE_NEW_FACT, static function (array $p): array {
        $p['dialogue'][5]['messages'][1]['text_target'] = 'Give him ibuprofen twice a day for five days.';

        return $p;
    }],
    'a rescue with no partner line before it' => [LessonCodes::RESCUE_NO_PREV, static function (array $p): array {
        $rescue = $p['dialogue'][5];
        $rescue['step'] = 1;
        $p['dialogue'][0] = $rescue;

        return $p;
    }],
    'two questions in one partner bubble' => [LessonCodes::PARTNER_TWO_QUESTIONS, static function (array $p): array {
        $p['dialogue'][1]['messages'][0]['text_target'] = 'When did it start, and did he lift anything heavy?';

        return $p;
    }],
    'a partner line of nineteen words' => [LessonCodes::PARTNER_TOO_LONG, static function (array $p): array {
        $p['dialogue'][4]['messages'][0]['text_target'] = 'It looks like a muscle strain from sports, so he should rest at home and use a warm heating pad.';

        return $p;
    }],
    'an empty closer' => [LessonCodes::PARTNER_CLOSER, static function (array $p): array {
        $p['dialogue'][7]['messages'][1]['text_target'] = 'Great!';

        return $p;
    }],
    'a check about what the learner said' => [LessonCodes::CHECK_ABOUT_LEARNER, static function (array $p): array {
        $p['dialogue'][0]['check']['text_target'] = 'What does the parent say?';

        return $p;
    }],
    'a right option copied from the partner' => [LessonCodes::CHECK_VERBATIM, static function (array $p): array {
        $p['dialogue'][1]['check']['options'][1]['text_target'] = 'Earlier this week';

        return $p;
    }],
    'an alternative the partner named, offered as wrong' => [LessonCodes::CHECK_LISTED_ALTERNATIVE_AS_WRONG, static function (array $p): array {
        $p['dialogue'][0]['check']['options'][1]['text_target'] = 'His upper back';

        return $p;
    }],
    'two listening questions' => [LessonCodes::LISTENING_COUNT, static function (array $p): array {
        array_pop($p['listening']['questions']);

        return $p;
    }],
    'two listening questions about one exchange' => [LessonCodes::LISTENING_SAME_EXCHANGE, static function (array $p): array {
        $p['listening']['questions'][2] = ['text_native' => 'Где у ребёнка болит спина?', 'options_native' => ['В шее', 'В пояснице', 'В плече'], 'correct_option_index' => 1, 'explanation_native' => 'Болит поясница.'];

        return $p;
    }],
    'no listening question about the learner\'s value' => [LessonCodes::LISTENING_NO_LEARNER_VALUE, static function (array $p): array {
        $p['listening']['questions'][0]['options_native'] = ['Голова', 'Колено', 'Живот'];

        return $p;
    }],
    'a slot asked with a wrong option of another kind' => [LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER, static function (array $p): array {
        $p['listening']['questions'][0]['options_native'] = ['Три дня', 'Поясница', 'Плечо'];

        return $p;
    }],
    'a free combination as a chunk' => [LessonCodes::VOCAB_FREE_COMBINATION, static function (array $p): array {
        $p['vocabulary'][4]['term_target'] = 'heavy things';

        return $p;
    }],
    'a plain everyday word' => [LessonCodes::VOCAB_EVERYDAY_WORD, static function (array $p): array {
        $p['vocabulary'][1]['term_target'] = 'work';

        return $p;
    }],
    'used_in names a place without the word' => [LessonCodes::VOCAB_USED_IN_WRONG, static function (array $p): array {
        $p['vocabulary'][0]['used_in'] = ['A2'];

        return $p;
    }],
    'most words only heard' => [LessonCodes::VOCAB_LEARNER_SHARE, static function (array $p): array {
        foreach ([0 => ['A1'], 1 => ['A3'], 2 => ['A4'], 5 => ['A7']] as $i => $refs) {
            $p['vocabulary'][$i]['used_in'] = $refs;
        }

        return $p;
    }],
    'a word inside another item' => [LessonCodes::VOCAB_NESTED, static function (array $p): array {
        $p['vocabulary'][1]['term_target'] = 'back';

        return $p;
    }],
    'a gendered past while the gender is unknown' => [LessonCodes::NATIVE_GENDERED_PAST, static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['text_native'] = 'Я заметил, что у него болит поясница.';

        return $p;
    }],
    'rule text in an image prompt' => [LessonCodes::IMAGE_PROMPT_RULE_TEXT, static function (array $p): array {
        $p['vocabulary'][0]['image_prompt'] = 'realistic photo of a lower back, no text';

        return $p;
    }],
    ];
}

dataset('one broken rule', lvBreaks());

it('counts the one rule a lesson breaks by its code', function (string $code, Closure $break) {
    expect(lvCodes($break(lvPayload())))->toContain($code);
})->with('one broken rule');

// A code with no row of its own is a code nothing proves it counts.
it('has a broken rule for every code it counts', function () {
    $named = array_map(static fn (array $row): string => $row[0], array_values(lvBreaks()));

    expect(array_values(array_diff(LessonCodes::all(), $named)))->toBe([]);
});

// Architect, after GEN-2a: «check.verbatim не считает числа/имена/предметы без пересказа». Catches a count that
// punishes a check for naming the amount, the medicine or the item the partner stated — and one that stops counting
// a real copy.
it('does not count a copied number, name or item of the lesson as a copy, and still counts a copied phrase', function () {
    $number = lvPayload();
    $number['dialogue'][7]['check']['options'][2]['text_target'] = 'After one week';
    $name = lvPayload();
    $name['dialogue'][4]['messages'][0]['text_target'] = 'It looks like a muscle strain, so he should rest and take Nurofen.';
    $name['dialogue'][4]['check']['options'][1]['text_target'] = 'Take Nurofen';
    $item = lvPayload();
    $item['dialogue'][0]['check']['options'][0]['text_target'] = 'His lower back';
    $copy = lvPayload();
    $copy['dialogue'][4]['check']['options'][1]['text_target'] = 'He should rest';

    expect(lvCodes($number))->not->toContain(LessonCodes::CHECK_VERBATIM)
        ->and(lvCodes($name))->not->toContain(LessonCodes::CHECK_VERBATIM)
        ->and(lvCodes($item))->not->toContain(LessonCodes::CHECK_VERBATIM)
        ->and(lvCodes($copy))->toContain(LessonCodes::CHECK_VERBATIM);
});

// Architect, after GEN-2a: «listening.distractor_not_filler считает только „не того рода“». Catches a count of every
// fair distractor that is not on the frame's list — and one that lets a wrong option of another kind pass.
it('counts a listening distractor only when it is of another kind than the slot', function () {
    $sameKind = lvPayload();
    $sameKind['listening']['questions'][0]['options_native'] = ['Живот', 'Поясница', 'Колено'];
    $timeSlot = lvPayload();
    $timeSlot['listening']['questions'][2] = ['text_native' => 'Когда началась боль?', 'options_native' => ['Неделю назад', 'Три дня назад', 'Через месяц'], 'correct_option_index' => 1, 'explanation_native' => 'Началось три дня назад.'];
    $timeSlotOtherKind = lvPayload();
    $timeSlotOtherKind['listening']['questions'][2] = ['text_native' => 'Когда началась боль?', 'options_native' => ['После футбола', 'Три дня назад', 'Через месяц'], 'correct_option_index' => 1, 'explanation_native' => 'Началось три дня назад.'];

    expect(lvCodes($sameKind))->not->toContain(LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER)
        ->and(lvCodes($timeSlot))->not->toContain(LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER)
        ->and(lvCodes($timeSlotOtherKind))->toContain(LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER);
});

it('reads a gendered past only while the learner\'s gender is unknown', function () {
    $p = lvPayload();
    $p['dialogue'][0]['messages'][1]['text_native'] = 'Я заметила, что у него болит поясница.';

    expect(lvCodes($p))->toContain(LessonCodes::NATIVE_GENDERED_PAST)
        ->and(lvCodes($p, VoiceGender::Female))->not->toContain(LessonCodes::NATIVE_GENDERED_PAST);
});

it('addresses every finding to its card', function () {
    $p = lvPayload();
    $p['dialogue'][0]['messages'][1]['speaking_key'] = 'in his';
    $p['phrases'][0]['slot']['fillers'][2]['in_dialogue'] = true;
    $p['dialogue'][1]['check']['options'][1]['text_target'] = 'Earlier this week';

    $addresses = array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", lvRun($p));

    expect($addresses)->toContain('key.no_content_word@B1')
        ->and($addresses)->toContain('filler.one_in_dialogue@p1.f3')
        ->and($addresses)->toContain('check.verbatim@x2.check');
});
