<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\Slot;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\SpeakingKey;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * THE SERVED LESSON (`lesson_day.v4.5`; наряды GEN-2a, GEN-2b и его доработка, docs/plan-v2.md §3а): the server reads
 * the learner's lines against their frames — which filler each says, glue and the closing mark aside — marks in the
 * dialogue exactly the fillers it says, takes every speaking key from the frame, and moves the right answers of checks
 * and listening off the places the model put them. Each test names the rule and the defect it catches.
 */

/** @return array<string, mixed> */
function laPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
}

function laLesson(array $payload): Lesson
{
    return (new LessonParser)->parse($payload);
}

function laServe(Lesson $answer, string $seed = 'scene'): Lesson
{
    return LessonAssembly::serve($answer, $seed, lessonPacks()->for('en'));
}

/** @return list<string> */
function laCodesAt(array $payload, string $code): array
{
    $found = (new LessonValidator)->run(laLesson($payload), lessonContext());

    return array_values(array_map(
        static fn (LessonViolation $v): string => $v->address,
        array_filter($found, static fn (LessonViolation $v): bool => $v->code === $code),
    ));
}

/** @param list<string> $fillers */
function laFrame(string $frame, array $fillers): Phrase
{
    return new Phrase('p1', ExchangeKind::Answer, $frame, '___', '', $fillers === [] ? null : new Slot('', array_map(
        static fn (string $f): Filler => new Filler($f, $f, '', false),
        $fillers,
    )));
}

// Canon: «Сервер собирает текст реплики из каркаса + наполнения сам и сверяет с text_target модели; расхождение —
// line.ne_frame». Catches a line that is none of its frame's sentences let through, a comparison that forgives more than
// the glue, and a rescue line held to a frame.
it('reads a learner line against its frame, keeps the glue, and counts a line the model wrote otherwise', function () {
    $p = laPayload();
    $p['dialogue'][1]['messages'][1]['text_target'] = 'It began three days ago.';

    $served = laServe(laLesson($p));

    expect($served->exchange(1)?->learner()?->textTarget)->toBe('It hurts in his lower back.')
        // «Okay, » is glue: the rest is «He will rest ___.» with «at home», the first letter lowered after it.
        ->and($served->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will rest at home.')
        // The model wrote another sentence: there is no filler to put it together with — it is served as written, and
        // the day is not dealt before a repair.
        ->and($served->exchange(2)?->learner()?->textTarget)->toBe('It began three days ago.')
        ->and($served->exchange(2)?->learner()?->filler)->toBeNull()
        ->and(laCodesAt($p, LessonCodes::LINE_NE_FRAME))->toBe(['B2'])
        // A rescue line has no frame: served as written.
        ->and($served->exchange(6)?->learner()?->textTarget)->toBe('Sorry, could you say that more slowly?')
        ->and(laCodesAt(laPayload(), LessonCodes::LINE_NE_FRAME))->toBe([]);
});

it('forgives the glue, one lowered letter and the closing mark, and nothing else', function () {
    $frame = laFrame('It hurts in his ___.', ['lower back']);

    expect(FrameText::line($frame, 'Yes, it hurts in his lower back.'))->toBe(['text' => 'Yes, it hurts in his lower back.', 'matches' => true, 'glue' => 'Yes, ', 'filler' => $frame->fillers()[0]])
        ->and(FrameText::line($frame, 'Well, doctor, it hurts in his lower back.')['matches'])->toBeTrue()
        ->and(FrameText::line($frame, 'I think that it hurts in his lower back.')['matches'])->toBeFalse()
        ->and(FrameText::line($frame, 'It hurts, in his lower back.')['matches'])->toBeFalse()
        ->and(FrameText::line($frame, 'It hurts in his lower back today.')['matches'])->toBeFalse()
        // A frame with a space before its mark is typography: the sentence has none.
        ->and(FrameText::fill('I work ___ .', 'from home'))->toBe('I work from home.')
        // No filler for a slot: nothing to read the line against, and it is served as written.
        ->and(FrameText::line(laFrame('It hurts in his ___.', []), 'It hurts.'))->toBe(['text' => 'It hurts.', 'matches' => false, 'glue' => '', 'filler' => null]);
});

// Доработка GEN-2b, п. 1: «после снятия клея с обеих сторон снимается знак конца предложения (. ! ? …); совпало — служит
// текст модели с её знаком; каркас без знака — frame.no_end_punct; фраза дня при каркасе без знака берёт знак от реплики».
// The airport day failed on five lines whose frames had no full stop. Catches a closing mark compared again (a correct
// line fatal for its frame's typography), the mark of the line lost from what is served, a frame without a mark let
// through uncounted, and a phrase of the day left without the mark its line has.
it('leaves the closing mark out of the comparison, serves the model\'s own mark and counts a frame written without one', function () {
    $frame = laFrame('I\'d like a ___, please', ['window seat', 'aisle seat']);
    $p = laPayload();
    $p['phrases'][0]['frame_target'] = 'It hurts in his ___';
    $p['phrases'][0]['frame_native'] = 'У него болит ___';
    $p['phrases'][3]['frame_target'] = 'He doesn\'t have a fever';
    $sceneId = PlanSceneId::generate();
    $terms = [];
    foreach (PlanTerm::fromLesson($sceneId, laServe(laLesson($p), $sceneId->value), static fn (): PlanTermId => PlanTermId::generate()) as $term) {
        $terms[$term->ref()] = $term;
    }

    expect(FrameText::line($frame, 'I\'d like a window seat, please.'))->toBe(['text' => 'I\'d like a window seat, please.', 'matches' => true, 'glue' => '', 'filler' => $frame->fillers()[0]])
        ->and(FrameText::line(laFrame('It hurts in his ___.', ['lower back']), 'Yes, it hurts in his lower back!')['matches'])->toBeTrue()
        ->and(FrameText::line(laFrame('Do we need ___?', ['an X-ray']), 'Do we need an X-ray')['matches'])->toBeTrue()
        ->and(laCodesAt($p, LessonCodes::LINE_NE_FRAME))->toBe([])
        ->and(laCodesAt($p, LessonCodes::FRAME_NO_END_PUNCT))->toBe(['p1', 'p4'])
        // A side with no mark at all is `frame.no_end_punct`, not a mismatch of marks.
        ->and(laCodesAt($p, LessonCodes::FRAME_NATIVE_PUNCT))->toBe([])
        ->and(laServe(laLesson($p))->exchange(1)?->learner()?->textTarget)->toBe('It hurts in his lower back.')
        ->and($terms['p1']->textTarget())->toBe('It hurts in his lower back.')
        ->and($terms['p1']->textNative())->toBe('У него болит поясница.')
        ->and($terms['p4']->textTarget())->toBe('He doesn\'t have a fever.')
        ->and(laCodesAt(laPayload(), LessonCodes::FRAME_NO_END_PUNCT))->toBe([]);
});

// Доработка GEN-2b, п. 2: «наполнение реплики находит сервер: перебрать наполнения slot.fillers её каркаса, собрать, сравнить
// с text_target; совпало несколько — первое по порядку каркаса; поле filler реплики в ответе модели не читается никем».
// The bank day failed where the model's filler repeated the frame's «my». Catches the model's field read anywhere — by
// the served line, by the marks the server serves, by the validator's marks, listening and repeats — and a match other
// than the first in the frame's order.
it('finds the filler of a line by its text among its frame\'s fillers, whatever the model wrote in the filler field', function () {
    $p = laPayload();
    // The model names a filler that is no filler of p1, and one of p3 the line does not say.
    $p['dialogue'][0]['messages'][1]['filler'] = 'his lower back';
    $p['dialogue'][2]['messages'][1]['filler'] = 'dull';
    // Exchange 8 says the filler exchange 7 said, while its field names another.
    $repeat = laPayload();
    $repeat['dialogue'][7]['messages'][0]['text_target'] = 'Do we need an X-ray?';
    $repeat['phrases'][5]['slot']['fillers'][1]['in_dialogue'] = false;
    $served = laServe(laLesson($p));

    expect($served->exchange(1)?->learner()?->filler)->toBe('lower back')
        ->and($served->exchange(3)?->learner()?->filler)->toBe('sharp')
        ->and(array_map(static fn (Filler $f): bool => $f->inDialogue, $served->phrase('p3')?->fillers() ?? []))->toBe([true, false, false])
        ->and(LessonAssembly::fillerOf(laLesson($p), laLesson($p)->exchange(3)?->learner() ?? throw new LogicException)?->target)->toBe('sharp')
        ->and(laCodesAt($p, LessonCodes::LINE_NE_FRAME))->toBe([])
        ->and(laCodesAt($p, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe([])
        ->and(laCodesAt($p, LessonCodes::LISTENING_NO_LEARNER_VALUE))->toBe([])
        ->and(laCodesAt($repeat, LessonCodes::EXCHANGE_REPEATS))->toBe(['x8'])
        // Two fillers make the same sentence: the first in the frame's order is the line's.
        ->and(FrameText::line(laFrame('___ hurts.', ['his back', 'His back']), 'His back hurts.')['filler']?->target)->toBe('his back');
});

// Доработка GEN-2b, п. 3: «speaking_key реплики собирает сервер: часть каркаса до ___ или после — со знаменательными словами
// (при равенстве — до окна; нет ни в одной или нет пакета — до окна как написана, пустая — после), ≤ 4 слов: у части до
// окна последние, у части после первые; без окна — первые 4 слова; rescue — ключ модели, если он в реплике, иначе первые 4
// слова реплики». Catches the model's key served, a key of five words, and the part without content words taken while
// the other part has them.
it('takes the speaking key of a line from its frame, never from the model, four words at most', function () {
    $en = lessonPacks()->for('en');
    $p = laPayload();
    $p['dialogue'][0]['messages'][1]['speaking_key'] = 'lower back';
    $served = laServe(laLesson($p));

    expect(SpeakingKey::ofFrame('It hurts in his ___.', $en))->toBe('It hurts in his')
        ->and(SpeakingKey::ofFrame('The ___ is too noisy.', $en))->toBe('is too noisy')
        ->and(SpeakingKey::ofFrame('Could you tell me more about ___?', $en))->toBe('tell me more about')
        ->and(SpeakingKey::ofFrame('I would like to book ___ for two nights.', $en))->toBe('would like to book')
        ->and(SpeakingKey::ofFrame('We need ___ before we can sign the lease.', $en))->toBe('before we can sign')
        ->and(SpeakingKey::ofFrame('Here is my ___.', $en))->toBe('Here is my')
        ->and(SpeakingKey::ofFrame('___, please.', $en))->toBe('please')
        ->and(SpeakingKey::ofFrame('He doesn\'t have a fever.', $en))->toBe('He doesn\'t have a')
        // No pack for the target: the part before the slot as written.
        ->and(SpeakingKey::ofFrame('The ___ is too noisy.', LanguagePack::none('en')))->toBe('The')
        ->and(SpeakingKey::ofLine('Sorry, could you say that more slowly?', 'more SLOWLY'))->toBe('more SLOWLY')
        ->and(SpeakingKey::ofLine('Sorry, could you say that more slowly?', 'slow down'))->toBe('Sorry, could you say')
        ->and($served->exchange(1)?->learner()?->speakingKey)->toBe('It hurts in his')
        ->and($served->exchange(3)?->learner()?->speakingKey)->toBe('The pain is')
        ->and($served->exchange(5)?->learner()?->speakingKey)->toBe('He will rest')
        ->and($served->exchange(6)?->learner()?->speakingKey)->toBe('more slowly');
});

// Canon v4.5 (FILLERS, NATURAL ORDER OF ONE VISIT): «a frame used in one exchange has exactly one in_dialogue filler;
// a frame used in two exchanges has two — one per exchange, and they are different», «no two exchanges ask the same
// thing». Catches marks that lie about what the dialogue says — a filler marked and never said, a filler said and not
// marked — a frame said twice with two marks counted as a breach, the same filler said twice left uncounted or
// counted as a mark instead of a repeated exchange, and a served lesson that keeps the model's marks.
it('marks in the dialogue exactly the fillers it says, and counts the same filler said twice as a repeated exchange', function () {
    $clean = laPayload();
    $markedNotSaid = laPayload();
    $markedNotSaid['phrases'][5]['slot']['fillers'][2]['in_dialogue'] = true;
    $saidNotMarked = laPayload();
    $saidNotMarked['phrases'][5]['slot']['fillers'][1]['in_dialogue'] = false;
    $sameTwice = laPayload();
    $sameTwice['dialogue'][7]['messages'][0]['text_target'] = 'Do we need an X-ray?';
    $sameTwice['phrases'][5]['slot']['fillers'][1]['in_dialogue'] = false;

    expect(laCodesAt($clean, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe([])
        ->and(laCodesAt($clean, LessonCodes::EXCHANGE_REPEATS))->toBe([])
        ->and(laCodesAt($markedNotSaid, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe(['p6.f3'])
        ->and(laCodesAt($saidNotMarked, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe(['p6.f2'])
        ->and(laCodesAt($sameTwice, LessonCodes::EXCHANGE_REPEATS))->toBe(['x8'])
        ->and(laCodesAt($sameTwice, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe([])
        // What is served marks what the lines say, whatever the model marked.
        ->and(array_map(static fn (Filler $f): bool => $f->inDialogue, laServe(laLesson($markedNotSaid))->phrase('p6')?->fillers() ?? []))->toBe([true, true, false])
        ->and(array_map(static fn (Filler $f): bool => $f->inDialogue, laServe(laLesson($saidNotMarked))->phrase('p6')?->fillers() ?? []))->toBe([true, true, false]);
});

// Canon: «Индексы правильных ответов (check и listening) перемешиваются при сборке». Catches a lesson served with the
// model's habit — every right answer first — and a shuffle that loses which option is right or deals a new order on
// every read.
it('moves every right answer off the place the model put it, keeping which option is right, the same way on every read', function () {
    $p = laPayload();
    foreach (array_keys($p['dialogue']) as $i) {
        $options = $p['dialogue'][$i]['check']['options'];
        $right = $options[$p['dialogue'][$i]['check']['correct_option_index']];
        $p['dialogue'][$i]['check']['options'] = [$right, ...array_values(array_filter($options, static fn (array $o): bool => $o !== $right))];
        $p['dialogue'][$i]['check']['correct_option_index'] = 0;
    }
    foreach (array_keys($p['listening']['questions']) as $i) {
        $q = $p['listening']['questions'][$i];
        $right = $q['options_native'][$q['correct_option_index']];
        $p['listening']['questions'][$i]['options_native'] = [$right, ...array_values(array_diff($q['options_native'], [$right]))];
        $p['listening']['questions'][$i]['correct_option_index'] = 0;
    }
    $answer = laLesson($p);

    $served = laServe($answer, '01M2SCENEFORSHUFFLE0000000');
    $again = laServe($answer, '01M2SCENEFORSHUFFLE0000000');

    $places = [
        ...array_map(static fn ($e): int => $e->check->correctOptionIndex, $served->exchanges),
        ...array_map(static fn ($q): int => $q->correctOptionIndex, $served->listening),
    ];
    expect(array_unique($places))->not->toBe([0])
        ->and(count(array_unique($places)))->toBe(3)
        ->and($served->toArray())->toBe($again->toArray());
    foreach ($served->exchanges as $i => $exchange) {
        expect($exchange->check->correctOption()?->textTarget)->toBe($answer->exchanges[$i]->check->correctOption()?->textTarget);
    }
    foreach ($served->listening as $i => $question) {
        expect($question->correctOption())->toBe($answer->listening[$i]->correctOption());
    }
});

it('writes a phrase as its frame said with the dialogue filler, and keeps the frame itself', function () {
    $sceneId = PlanSceneId::generate();
    $terms = PlanTerm::fromLesson($sceneId, laServe(laLesson(laPayload()), $sceneId->value), static fn (): PlanTermId => PlanTermId::generate());
    $byRef = [];
    foreach ($terms as $term) {
        $byRef[$term->ref()] = $term;
    }

    expect(count($terms))->toBe(14)
        ->and($byRef['p5']->kind())->toBe(TermKind::Phrase)
        ->and($byRef['p5']->textTarget())->toBe('He will rest at home.')
        ->and($byRef['p5']->textNative())->toBe('Он будет отдыхать дома.')
        ->and($byRef['p5']->pronunciationNative())->toBe('хи уил рэст эт хоум')
        // The line lends the phrase its key (the server's, off the frame) and example — with its glue: it is what was said.
        ->and($byRef['p5']->speakingKey())->toBe('He will rest')
        ->and($byRef['p5']->exampleTarget())->toBe('Okay, he will rest at home.')
        ->and($byRef['p5']->frame()?->frameTarget)->toBe('He will rest ___.')
        ->and($byRef['p6']->textTarget())->toBe('Do we need an X-ray?')
        ->and(count($byRef['p6']->frame()->slot->fillers ?? []))->toBe(3)
        ->and($byRef['p4']->textTarget())->toBe('He doesn\'t have a fever.')
        ->and($byRef['v5']->usedIn())->toBe(['A5', 'A6'])
        ->and($byRef['v5']->exampleTarget())->toBe('It looks like a muscle strain, so he should rest and use a heating pad.');
});
