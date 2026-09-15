<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE LESSON VALIDATOR, CODE BY CODE (`lesson_day.v4.5`, docs/plan-v2.md §4).
 *
 * The clean fixture lesson breaks nothing; every row below breaks ONE rule of the prompt in it and names
 * the code that must count the breach — the defect each code exists to catch, run against the code. A
 * rule the validator stops reading fails its own row. The words of both languages are the deployment's packs
 * (`config/lesson/lang`): ru the learner's, en the target.
 */

/** @return array<string, mixed> */
function lvPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8));
}

/** @return list<LessonViolation> */
function lvRun(array $payload, ?VoiceGender $gender = null, ?LessonValidationContext $context = null): array
{
    return (new LessonValidator)->run((new LessonParser)->parse($payload), $context ?? lessonContext('ru', 'en', $gender));
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
    'an exchange that says a frame with the filler an earlier one said' => [LessonCodes::EXCHANGE_REPEATS, static function (array $p): array {
        $p['dialogue'][7]['messages'][0]['filler'] = 'an X-ray';
        $p['dialogue'][7]['messages'][0]['text_target'] = 'Do we need an X-ray?';

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
    'a frame that leans on a pronoun it does not name' => [LessonCodes::FRAME_UNRESOLVED_PRONOUN, static function (array $p): array {
        $p['phrases'][5]['frame_target'] = 'Do we need ___ for it?';

        return $p;
    }],
    'a native frame with a word that agrees with the slot' => [LessonCodes::FRAME_NATIVE_AGREEMENT, static function (array $p): array {
        $p['phrases'][0]['frame_native'] = 'У него болит этот ___.';

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
    'a filler that is a clause' => [LessonCodes::FILLER_IS_CLAUSE, static function (array $p): array {
        $p['phrases'][4]['slot']['fillers'][2]['target'] = 'if he feels better';

        return $p;
    }],
    'an article of a filler that does not fit its noun' => [LessonCodes::FILLER_ARTICLE_SEAM, static function (array $p): array {
        $p['phrases'][5]['slot']['fillers'][2]['target'] = 'a extra note';

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
    'a line that says the partner\'s statement back' => [LessonCodes::LEARNER_RESTATES_PARTNER, static function (array $p): array {
        $p['dialogue'][4]['messages'][0]['text_target'] = 'He should rest at home.';

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

// A code with no row of its own is a code nothing proves it counts. The seam judge's code is a model's, not a rule's
// (LessonSeamJudge, `LessonObservationTest`).
it('has a broken rule for every code it counts', function () {
    $named = array_map(static fn (array $row): string => $row[0], array_values(lvBreaks()));

    expect(array_values(array_diff(LessonCodes::validated(), $named)))->toBe([])
        ->and(LessonCodes::JUDGED)->toBe([LessonCodes::FILLER_NATIVE_SEAM]);
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
    $p['dialogue'][6]['messages'][1]['text_target'] = 'Do you want an X-ray?';

    $addresses = array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", lvRun($p));

    expect($addresses)->toContain('key.no_content_word@B1')
        ->and($addresses)->toContain('filler.one_in_dialogue@p1.f3')
        ->and($addresses)->toContain('check.verbatim@x2.check')
        // A closing question is the whole exchange's: the repair takes the exchange, whoever asked.
        ->and($addresses)->toContain('exchange.second_question@x7');
});

// Canon GEN-2b: «правила не про английский и русский, а про пару (target_lang, native_lang); код, для которого пакета
// нет, — пропуск проверки со счётчиком lang.pack_missing, НЕ находка». Catches a validator that reads a language it
// has no pack for with another language's words (a Romanian learner's lines judged by Russian rules), that counts a
// skip as a finding, that crashes on a pack with no keys — and one that skips checks that need no pack.
it('skips a check whose language has no pack and writes the skip down, finding nothing of it', function () {
    $p = lvPayload();
    $p['vocabulary'][1]['pronunciation_native'] = 'sharp';
    $p['dialogue'][0]['messages'][1]['text_native'] = 'Я заметил, что у него болит поясница.';
    $p['phrases'][0]['frame_native'] = 'У него болит этот ___.';
    $p['listening']['questions'][0]['options_native'] = ['Три дня', 'Поясница', 'Плечо'];
    $p['dialogue'][0]['messages'][1]['speaking_key'] = 'in his';

    $ru = lessonContext('ru', 'en');
    $ro = lessonContext('ro', 'en');
    $codes = static fn (LessonValidationContext $context): array => array_values(array_unique(array_map(
        static fn (LessonViolation $v): string => $v->code,
        lvRun($p, null, $context),
    )));
    $native = [
        LessonCodes::PRONUNCIATION_SCRIPT, LessonCodes::FRAME_NATIVE_PUNCT, LessonCodes::FRAME_NATIVE_AGREEMENT,
        LessonCodes::LISTENING_SAME_EXCHANGE, LessonCodes::LISTENING_NO_LEARNER_VALUE, LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER,
        LessonCodes::NATIVE_GENDERED_PAST,
    ];

    expect($codes($ru))->toContain(LessonCodes::PRONUNCIATION_SCRIPT, LessonCodes::NATIVE_GENDERED_PAST, LessonCodes::FRAME_NATIVE_AGREEMENT, LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER)
        ->and($ru->skips->codes())->toBe([])
        ->and(array_intersect($codes($ro), $native))->toBe([])
        ->and($ro->skips->codes())->toEqualCanonicalizing($native)
        // The target's own rules and the rules that need no language still run for the Romanian learner.
        ->and($codes($ro))->toContain(LessonCodes::KEY_NO_CONTENT_WORD);

    // No pack at all, on either side: nothing but the language-free rules, and no rule reads a key it was not given.
    $none = new LessonValidationContext(8, 8, LanguagePack::none('xx'), LanguagePack::none('yy'));
    $found = lvRun(lvBreaks()['a key of function words'][1](lvPayload()), null, $none);
    expect($found)->toBe([])
        ->and($none->skips->codes())->toContain(LessonCodes::KEY_NO_CONTENT_WORD, LessonCodes::EXCHANGE_SECOND_QUESTION, LessonCodes::FILLER_UNGRAMMATICAL);
    foreach (lvBreaks() as [$code, $break]) {
        lvRun($break(lvPayload()), null, new LessonValidationContext(8, 8, LanguagePack::none('xx'), LanguagePack::none('yy')));
    }
});

// Canon GEN-2b: «frame.native_agreement — слово native-каркаса, согласующееся с окном (пакет native: суффиксы/список)».
// Catches a rule that keeps its own word list instead of the learner's language's pack — a pack without «разрешён»
// still finding it — and one that reads agreement anywhere in the frame instead of at the slot («У моего сына ___»).
it('reads the agreement of a native frame from the native pack, at the slot', function () {
    $p = lvPayload();
    $p['phrases'][0]['frame_native'] = '___ ему разрешён.';
    $p['phrases'][2]['frame_native'] = 'У моего сына боль ___, когда он наклоняется.';

    $ru = require dirname(__DIR__, 3).'/config/lesson/lang/ru.php';
    $without = $ru;
    $without['agreement']['short_forms'] = array_values(array_diff($ru['agreement']['short_forms'], ['разрешён']));
    $at = static fn (array $pack): array => array_values(array_map(
        static fn (LessonViolation $v): string => $v->address,
        array_filter(
            lvRun($p, null, new LessonValidationContext(8, 8, (new LanguagePacks(['ru' => $pack]))->for('ru'), lessonPacks()->for('en'))),
            static fn (LessonViolation $v): bool => $v->code === LessonCodes::FRAME_NATIVE_AGREEMENT,
        ),
    ));

    expect($at($ru))->toBe(['p1'])
        ->and($at($without))->toBe([]);
});

// Canon v4.5 (SPEAKING SUPPORT): «Prefer a key that contains a content word. When the frame part outside the slot has
// no content word at all, the key is the frame part up to the slot, exactly as written». Catches the v4.4 reading — every
// key without a content word counted, «Here is» on «Here is ___» too — and a key off the frame part let through.
it('asks a content word of a key only where the frame part has one, and the frame up to the slot where it has none', function () {
    $frame = static function (string $key): array {
        $p = lvPayload();
        $p['phrases'][5]['frame_target'] = 'What about ___?';
        $p['phrases'][5]['frame_native'] = 'А как насчёт ___?';
        foreach ([6 => 'an X-ray', 7 => 'a follow-up appointment'] as $i => $filler) {
            $p['dialogue'][$i]['messages'][0]['text_target'] = "What about {$filler}?";
            $p['dialogue'][$i]['messages'][0]['speaking_key'] = $key;
        }

        return array_values(array_map(
            static fn (LessonViolation $v): string => $v->address,
            array_filter(lvRun($p), static fn (LessonViolation $v): bool => $v->code === LessonCodes::KEY_NO_CONTENT_WORD),
        ));
    };

    expect($frame('What about'))->toBe([])
        ->and($frame('about'))->toBe(['B7', 'B8'])
        ->and(lvCodes(lvBreaks()['a key of function words'][1](lvPayload())))->toContain(LessonCodes::KEY_NO_CONTENT_WORD);
});

// Live day GEN-2b (rent, v4.5): «I can move in ___» + «in June» failed the day as «a word is doubled at the seam» —
// English says the particle and the preposition both. Catches a fatal count of a sentence that is right, and a doubled
// word let through where it is wrong («my my»).
it('does not count a particle before a preposition of the same spelling as a doubled word, and still counts «my my»', function () {
    $particle = lvPayload();
    $particle['phrases'][4]['frame_target'] = 'He can move in ___.';
    $particle['phrases'][4]['slot']['fillers'][2]['target'] = 'in June';
    $possessive = lvPayload();
    $possessive['phrases'][0]['slot']['fillers'][1]['target'] = 'his neck';
    $at = static fn (array $p): array => array_values(array_map(
        static fn (LessonViolation $v): string => $v->address,
        array_filter(lvRun($p), static fn (LessonViolation $v): bool => $v->code === LessonCodes::FILLER_UNGRAMMATICAL),
    ));

    expect($at($particle))->toBe([])
        ->and($at($possessive))->toBe(['p1.f2']);
});

// Live day GEN-2b (bank, airport-ro): P2R on the cheap model answered `exchange.second_question` by deleting the mark —
// «Sure. May I see your passport» — and the day passed with a broken line. Canon GEN-2b: «детекция вопроса» is the
// target's pack. Catches a question known only by its mark, and a statement read as a question («Have a nice day.»).
it('knows a closing question by its word order when its question mark is gone, and a statement stays a statement', function () {
    $unmarked = lvPayload();
    $unmarked['dialogue'][6]['messages'][1]['text_target'] = 'Sure. May I see your referral first';
    $statement = lvPayload();
    $statement['dialogue'][6]['messages'][1]['text_target'] = 'No, an X-ray is not needed. Have a nice day.';
    $at = static fn (array $p): array => array_values(array_map(
        static fn (LessonViolation $v): string => $v->address,
        array_filter(lvRun($p), static fn (LessonViolation $v): bool => $v->code === LessonCodes::EXCHANGE_SECOND_QUESTION),
    ));

    expect($at($unmarked))->toBe(['x7'])
        ->and($at($statement))->toBe([])
        ->and($at(lvPayload()))->toBe([]);
});
