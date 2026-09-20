<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
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
 * THE LESSON VALIDATOR, CODE BY CODE (`lesson_day.v4.7`, docs/plan-v2.md §4).
 *
 * The clean fixture lesson breaks nothing but the one v4.6 rule it predates ({@see planFixtureWarnings()}); every row below
 * breaks ONE rule of the prompt in it and names the code that must count the breach — the defect each code exists to
 * catch, run against the code. A rule the validator stops reading fails its own row. The words of both languages are the
 * deployment's packs (`config/lesson/lang`): ru the learner's, en the target. What the story so far forbids is read
 * against an earlier day of the plan (наряд GEN-3).
 */

/** @return array<string, mixed> */
function lvPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
}

/** @return list<LessonViolation> */
function lvRun(array $payload, ?VoiceGender $gender = null, ?LessonValidationContext $context = null): array
{
    return (new LessonValidator)->run((new LessonParser)->parse($payload), $context ?? lessonContext('ru', 'en', $gender));
}

/** @return list<string> */
function lvCodes(array $payload, ?VoiceGender $gender = null, ?LessonValidationContext $context = null): array
{
    return array_values(array_unique(array_map(static fn (LessonViolation $v): string => $v->code, lvRun($payload, $gender, $context))));
}

/** @return list<string> `code@address` of every finding */
function lvAt(array $payload, ?LessonValidationContext $context = null): array
{
    return array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", lvRun($payload, null, $context));
}

/** The clean lesson told in an order where its frame p6 is never said twice in a row ({@see planCleanLesson()}). */
function lvApart(): array
{
    return planCleanLesson(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
}

it('finds nothing in a lesson that keeps every rule', function () {
    expect(lvRun(lvApart()))->toBe([])
        ->and(lvAt(lvPayload()))->toBe(planFixtureWarnings());
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
    // A symbol leaves the script without leaving the ALPHABET: the warning alone, not the fatal code.
    'a reading outside the script but in its own letters' => [LessonCodes::PRONUNCIATION_SCRIPT, static function (array $p): array {
        $p['vocabulary'][1]['pronunciation_native'] = 'шарп +';

        return $p;
    }],
    // Наряд BACK-TAILS-1 §3.2: a LETTER of another writing is fatal — «ֆоутoуз» is nothing the learner can read.
    'a reading with letters of another writing' => [LessonCodes::PRONUNCIATION_FOREIGN_SCRIPT, static function (array $p): array {
        $p['vocabulary'][1]['pronunciation_native'] = 'ֆоутoуз';

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
    'a frame written without its closing mark' => [LessonCodes::FRAME_NO_END_PUNCT, static function (array $p): array {
        $p['phrases'][1]['frame_target'] = 'It started ___';

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
    'an abbreviation as a word of the day' => [LessonCodes::VOCAB_ABBREVIATION, static function (array $p): array {
        $p['vocabulary'][5]['term_target'] = 'MRI';

        return $p;
    }],
    'two frames of one native pattern' => [LessonCodes::FRAME_TWIN, static function (array $p): array {
        $p['phrases'][4]['frame_native'] = 'У него болит ___.';

        return $p;
    }],
    'one frame in two exchanges in a row' => [LessonCodes::FRAME_ADJACENT_REPEAT, static function (array $p): array {
        $p['dialogue'][1]['messages'][1]['phrase_id'] = 'p1';
        $p['dialogue'][1]['messages'][1]['text_target'] = 'It hurts in his neck.';

        return $p;
    }],
    ];
}

/**
 * THE STORY SO FAR (наряд GEN-3): a description → the code, the break of the clean lesson, and the day the learner had
 * before it.
 *
 * @return array<string, array{0: string, 1: Closure, 2: Closure(): LessonValidationContext}>
 */
function lvStoryBreaks(): array
{
    $dayOne = static fn (): LessonValidationContext => lessonContext('ru', 'en', null, new EarlierDays([planEarlierDay()]));

    return [
        'a word the learner learned on day 1' => [LessonCodes::VOCAB_KNOWN_REPEAT, static fn (array $p): array => $p, $dayOne],
        'a frame the learner learned on day 1' => [LessonCodes::FRAME_KNOWN_REPEAT, static fn (array $p): array => $p, $dayOne],
        'a native pattern of a frame of day 1, said another way in the target' => [LessonCodes::FRAME_KNOWN_NATIVE_REPEAT, static function (array $p): array {
            $p['phrases'][1]['frame_target'] = 'The pain began ___.';
            $p['dialogue'][1]['messages'][1]['text_target'] = 'The pain began three days ago.';

            return $p;
        }, $dayOne],
        'a partner of the same role with another voice than on day 1' => [LessonCodes::ROLE_GENDER_CHANGED, static function (array $p): array {
            $p['role_gender'] = 'male';

            return $p;
        }, $dayOne],
    ];
}

dataset('one thing an earlier day taught', lvStoryBreaks());

it('counts what the story so far already taught by its code', function (string $code, Closure $break, Closure $context) {
    expect(lvCodes($break(lvPayload()), null, $context()))->toContain($code)
        ->and(lvCodes($break(lvPayload())))->not->toContain($code);
})->with('one thing an earlier day taught');

dataset('one broken rule', lvBreaks());

it('counts the one rule a lesson breaks by its code', function (string $code, Closure $break) {
    expect(lvCodes($break(lvPayload())))->toContain($code);
})->with('one broken rule');

// A code with no row of its own is a code nothing proves it counts. The seam judge's code is a model's, not a rule's
// (LessonSeamJudge, `LessonObservationTest`). Доработка GEN-2b: no code is about the speaking key any more, the key is the
// server's. Наряд GEN-3 added seven codes; наряд BACK-TAILS-1 §3.2 one more — fifty-eight in all.
it('has a broken rule for every one of its fifty-eight codes', function () {
    $named = array_map(static fn (array $row): string => $row[0], [...array_values(lvBreaks()), ...array_values(lvStoryBreaks())]);

    expect(array_values(array_diff(LessonCodes::validated(), $named)))->toBe([])
        ->and(count(LessonCodes::all()))->toBe(58)
        ->and(count(array_unique(LessonCodes::all())))->toBe(58)
        ->and(array_filter(LessonCodes::all(), static fn (string $code): bool => str_starts_with($code, 'key.')))->toBe([])
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
    $p['phrases'][1]['frame_target'] = 'It started ___';
    $p['phrases'][0]['slot']['fillers'][2]['in_dialogue'] = true;
    $p['dialogue'][1]['check']['options'][1]['text_target'] = 'Earlier this week';
    $p['dialogue'][6]['messages'][1]['text_target'] = 'Do you want an X-ray?';

    $addresses = array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", lvRun($p));

    expect($addresses)->toContain('frame.no_end_punct@p2')
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
    $p['phrases'][4]['slot']['fillers'][2]['target'] = 'if he feels better';

    $ru = lessonContext('ru', 'en');
    $ro = lessonContext('ro', 'en');
    $codes = static fn (LessonValidationContext $context): array => array_values(array_unique(array_map(
        static fn (LessonViolation $v): string => $v->code,
        lvRun($p, null, $context),
    )));
    // `frame.no_end_punct` reads both sides: the target frame is still read for the Romanian learner, the native one is not.
    $native = [
        LessonCodes::PRONUNCIATION_SCRIPT, LessonCodes::PRONUNCIATION_FOREIGN_SCRIPT,
        LessonCodes::FRAME_NO_END_PUNCT, LessonCodes::FRAME_NATIVE_PUNCT, LessonCodes::FRAME_NATIVE_AGREEMENT,
        LessonCodes::LISTENING_SAME_EXCHANGE, LessonCodes::LISTENING_NO_LEARNER_VALUE, LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER,
        LessonCodes::NATIVE_GENDERED_PAST,
    ];

    expect($codes($ru))->toContain(LessonCodes::PRONUNCIATION_SCRIPT, LessonCodes::NATIVE_GENDERED_PAST, LessonCodes::FRAME_NATIVE_AGREEMENT, LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER)
        ->and($ru->skips->codes())->toBe([])
        ->and(array_intersect($codes($ro), $native))->toBe([])
        ->and($ro->skips->codes())->toEqualCanonicalizing($native)
        // The target's own rules and the rules that need no language still run for the Romanian learner.
        ->and($codes($ro))->toContain(LessonCodes::FILLER_IS_CLAUSE);

    // No pack at all, on either side: nothing but the language-free rules, and no rule reads a key it was not given.
    $none = new LessonValidationContext(8, 8, LanguagePack::none('xx'), LanguagePack::none('yy'));
    $found = lvRun(lvBreaks()['a filler that is a clause'][1](lvPayload()), null, $none);
    expect(array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", $found))->toBe(planFixtureWarnings())
        ->and($none->skips->codes())->toContain(LessonCodes::FILLER_IS_CLAUSE, LessonCodes::EXCHANGE_SECOND_QUESTION, LessonCodes::FILLER_UNGRAMMATICAL);
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

// Наряд GEN-3, v4.6: «a frame used twice … is not used in two exchanges in a row». Catches a count of a frame said twice
// with an exchange between (the lesson's own «say the pattern twice» — no warning), and a lost count of two in a row.
it('counts a frame in two exchanges in a row at the later one, and not a frame said twice apart', function () {
    $atAdjacent = static fn (array $p): array => array_values(array_filter(lvAt($p), static fn (string $f): bool => str_starts_with($f, LessonCodes::FRAME_ADJACENT_REPEAT.'@')));

    // A rescue between two exchanges on one frame keeps them apart: 5 and 7 are not in a row.
    $acrossRescue = lvApart();
    $acrossRescue['dialogue'][6]['messages'][1] = [...$acrossRescue['dialogue'][6]['messages'][1], 'phrase_id' => 'p5', 'filler' => 'for two days', 'text_target' => 'He will rest for two days.'];

    expect($atAdjacent(lvPayload()))->toBe(['frame.adjacent_repeat@x8'])
        ->and($atAdjacent(lvApart()))->toBe([])
        ->and($atAdjacent($acrossRescue))->toBe([]);
});

// Наряд GEN-3, v4.6 FRAMES: «one pattern = one frame … the TARGET_LANGUAGE pattern or the NATIVE_LANGUAGE pattern». Catches
// a twin missed because the case, the spaces or the closing mark differ, a twin by the native pattern alone missed, and
// one frame said in two exchanges (p6) taken for a twin.
it('knows two frames of one pattern in either language, case, spaces and the closing mark aside, and not one frame said twice', function () {
    $target = lvApart();
    $target['phrases'][4]['frame_target'] = 'it  hurts in his ___';
    $native = lvApart();
    $native['phrases'][4]['frame_native'] = 'у него   болит ___';

    expect(lvAt($target))->toContain('frame.twin@p5')
        ->and(lvAt($native))->toContain('frame.twin@p5')
        ->and(lvAt(lvApart()))->toBe([]);
});

// Наряд GEN-3: «vocab.known_repeat / frame.known_repeat — совпадает с термином / каркасом любого прошлого готового дня
// плана; нормализация та же, что у сверки реплика↔каркас: регистр, пробелы, знак конца не участвует; frame.known_native_repeat
// — совпал только frame_native». Catches a repeat missed for a capital letter, a space or a full stop, a native-only
// match counted as the fatal code, a day with no earlier days finding anything, and a finding without its card.
it('holds the words and frames an earlier day taught, case, spaces and the closing mark aside, and a native pattern alone only warns', function () {
    $taught = lvPayload();
    $taught['vocabulary'][0]['term_target'] = 'Lower  back';
    $taught['phrases'][0]['frame_target'] = 'IT HURTS IN HIS ___';
    $taught['phrases'][0]['slot']['fillers'][0]['target'] = 'Lower  back';
    $taught['dialogue'][0]['messages'][1]['text_target'] = 'IT HURTS IN HIS Lower  back';
    $dayOne = lessonContext('ru', 'en', null, new EarlierDays([planEarlierDay()]));

    $found = lvAt($taught, $dayOne);
    $known = static fn (string $code): array => array_values(array_filter($found, static fn (string $f): bool => str_starts_with($f, $code.'@')));

    expect($known(LessonCodes::VOCAB_KNOWN_REPEAT))->toBe(['vocab.known_repeat@v1', 'vocab.known_repeat@v2', 'vocab.known_repeat@v3', 'vocab.known_repeat@v4', 'vocab.known_repeat@v5', 'vocab.known_repeat@v6', 'vocab.known_repeat@v7', 'vocab.known_repeat@v8'])
        ->and($known(LessonCodes::FRAME_KNOWN_REPEAT))->toBe(['frame.known_repeat@p1', 'frame.known_repeat@p2', 'frame.known_repeat@p3', 'frame.known_repeat@p4', 'frame.known_repeat@p5', 'frame.known_repeat@p6'])
        ->and($known(LessonCodes::FRAME_KNOWN_NATIVE_REPEAT))->toBe([])
        ->and(array_values(array_filter(
            lvAt(lvStoryBreaks()['a native pattern of a frame of day 1, said another way in the target'][1](lvPayload()), $dayOne),
            static fn (string $f): bool => str_contains($f, '@p2') && str_starts_with($f, 'frame.known'),
        )))->toBe(['frame.known_native_repeat@p2'])
        ->and(array_filter(lvAt($taught), static fn (string $f): bool => str_starts_with($f, 'vocab.known') || str_starts_with($f, 'frame.known')))->toBe([]);
});

// Наряд GEN-3: «role_gender.changed — пол собеседника отличается от прошлого дня с той же ролью». Catches a partner of
// ANOTHER role counted (a nurse after a female doctor may be a man), a same gender counted, and a changed voice missed.
it('reads the partner\'s gender only against an earlier day of the same role', function () {
    $male = lvPayload();
    $male['role_gender'] = 'male';
    $days = new EarlierDays([planEarlierDay(1, null, 'doctor', VoiceGender::Female)]);

    expect(lvAt($male, lessonContext('ru', 'en', null, $days, 'Doctor')))->toContain('role_gender.changed@lesson')
        ->and(lvCodes($male, null, lessonContext('ru', 'en', null, $days, 'Nurse')))->not->toContain(LessonCodes::ROLE_GENDER_CHANGED)
        ->and(lvCodes(lvPayload(), null, lessonContext('ru', 'en', null, $days, 'Doctor')))->not->toContain(LessonCodes::ROLE_GENDER_CHANGED);
});

// Наряд GEN-3: «vocab.abbreviation — term_target аббревиатура или акроним (две и больше заглавных подряд, с точками или слэшем:
// API, CI/CD, U.S.)»; доработка: «предупреждение — судит модель, код только считает». Catches an acronym word of the day left
// uncounted — with dots, with a slash, inside a chunk, in any alphabet — and a word with one capital (X-ray, iPhone) or a
// hyphen (Wi-Fi) counted as one.
it('counts an abbreviation or an acronym as a word of the day, and not a word with one capital', function () {
    $at = static function (string $term): bool {
        $p = lvApart();
        $p['vocabulary'][5]['term_target'] = $term;

        return in_array('vocab.abbreviation@v6', lvAt($p), true);
    };

    expect(array_map($at, ['API', 'CI/CD', 'U.S.', 'HR manager', 'ЖКХ']))->toBe([true, true, true, true, true])
        ->and(array_map($at, ['X-ray', 'iPhone', 'Wi-Fi', 'sick note']))->toBe([false, false, false, false]);
});

// Live day CHECK-1 («Визит к ветеринару», day 1): «I'd like the ___ appointment.» with «3 p.m.» and «5:30 p.m.» failed the
// day as «the filler carries its own punctuation» — the dot of an abbreviation read as a sentence's. Canon: a whole
// sentence in the slot («See you tomorrow.», «Yes?») is still fatal; «3 p.m.», «5:30 p.m.», «Dr. Smith», «e.g.» are no
// finding; a comma, a semicolon or a colon at the end stay what they were. Catches the bare regex on the dot, a rule that
// lets a real sentence through, and one that reads the list where the pack has none.
it('lets a filler that ends with an abbreviation\'s dot through, and still holds a sentence or a comma in the slot', function () {
    $at = static function (string $filler, ?LessonValidationContext $context = null): array {
        $p = lvPayload();
        $p['phrases'][4]['slot']['fillers'][2]['target'] = $filler;

        return array_values(array_map(
            static fn (LessonViolation $v): string => $v->address,
            array_filter(lvRun($p, null, $context), static fn (LessonViolation $v): bool => $v->code === LessonCodes::FILLER_UNGRAMMATICAL),
        ));
    };

    expect(array_map($at, ['3 p.m.', '5:30 p.m.', 'Dr. Smith', 'e.g.', 'at home']))->toBe([[], [], [], [], []])
        ->and(array_map($at, ['See you tomorrow.', 'Yes?', 'at home,', 'at home;', 'at home:']))->toBe([['p5.f3'], ['p5.f3'], ['p5.f3'], ['p5.f3'], ['p5.f3']]);

    // A target pack that lists no abbreviations: every dot ends a sentence, and «3 p.m.» is a sentence in the slot.
    $en = require dirname(__DIR__, 3).'/config/lesson/lang/en.php';
    unset($en['abbreviations']);
    $bare = new LessonValidationContext(8, 8, lessonPacks()->for('ru'), (new LanguagePacks(['en' => $en]))->for('en'));
    expect($at('3 p.m.', $bare))->toBe(['p5.f3']);

    // A target pack with every seam key but `sentence_ends`: the check asks the pack first and is skipped whole — it
    // neither throws nor reads a full stop it has no rule for.
    $unmarked = require dirname(__DIR__, 3).'/config/lesson/lang/en.php';
    $unmarked['sentence_ends'] = null;
    $skipped = new LessonValidationContext(8, 8, lessonPacks()->for('ru'), (new LanguagePacks(['en' => $unmarked]))->for('en'));
    expect($at('See you tomorrow.', $skipped))->toBe([])
        ->and($skipped->skips->codes())->toContain(LessonCodes::FILLER_UNGRAMMATICAL);
});

// Live day CHECK-1: «We have 3 p.m. and 5:30 p.m. today.» was counted as three sentences (`partner.too_long`). Canon: the
// partner's sentences are counted by the same rule of where a sentence ends. Catches a count by every dot, and one that
// no longer counts real sentences.
it('counts the partner\'s sentences by where a sentence ends, an abbreviation\'s dot ending none', function () {
    $at = static function (string $line): array {
        $p = lvPayload();
        $p['dialogue'][4]['messages'][0]['text_target'] = $line;

        return array_values(array_map(
            static fn (LessonViolation $v): string => $v->address,
            array_filter(lvRun($p), static fn (LessonViolation $v): bool => $v->code === LessonCodes::PARTNER_TOO_LONG),
        ));
    };

    expect($at('We have 3 p.m. and 5:30 p.m. today.'))->toBe([])
        ->and($at('Ask Dr. Smith. He is here today.'))->toBe([])
        ->and($at("I'm here. Are you? Yes."))->toBe(['A5']);
});
