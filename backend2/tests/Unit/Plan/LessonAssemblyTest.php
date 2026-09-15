<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * THE SERVED LESSON (`lesson_day.v4.5`; наряд GEN-2a, docs/plan-v2.md §3): the server puts the learner's
 * lines together from frames and fillers, marks in the dialogue exactly the fillers it says, and moves
 * the right answers of checks and listening off the places the model put them. Each test names the rule
 * and the defect it catches.
 */

/** @return array<string, mixed> */
function laPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8));
}

function laLesson(array $payload): Lesson
{
    return (new LessonParser)->parse($payload);
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

// Canon: «Сервер собирает текст реплики из каркаса + наполнения сам и сверяет с text_target модели; расхождение —
// line.ne_frame». Catches a server that serves the model's words — the learner practising a frame the dialogue
// does not say — and a comparison that forgives more than the glue.
it('puts a learner line together from its frame and filler, keeps the glue, and counts a line the model wrote otherwise', function () {
    $p = laPayload();
    $p['dialogue'][1]['messages'][1]['text_target'] = 'It began three days ago.';

    $served = LessonAssembly::serve(laLesson($p), 'scene');

    expect($served->exchange(1)?->learner()?->textTarget)->toBe('It hurts in his lower back.')
        // «Okay, » is glue: the rest is «He will rest ___.» with «at home», the first letter lowered after it.
        ->and($served->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will rest at home.')
        // The model wrote another sentence: the learner gets the frame with its filler.
        ->and($served->exchange(2)?->learner()?->textTarget)->toBe('It started three days ago.')
        ->and(laCodesAt($p, LessonCodes::LINE_NE_FRAME))->toBe(['B2'])
        // A rescue line has no frame: served as written.
        ->and($served->exchange(6)?->learner()?->textTarget)->toBe('Sorry, could you say that more slowly?')
        ->and(laCodesAt(laPayload(), LessonCodes::LINE_NE_FRAME))->toBe([]);
});

it('forgives the glue and one lowered letter, and nothing else', function () {
    $frame = new Phrase('p1', ExchangeKind::Answer, 'It hurts in his ___.', 'У него болит ___.', '', null);

    expect(FrameText::line($frame, 'lower back', 'Yes, it hurts in his lower back.'))->toBe(['text' => 'Yes, it hurts in his lower back.', 'matches' => true, 'glue' => 'Yes, '])
        ->and(FrameText::line($frame, 'lower back', 'It hurts in his lower back!')['matches'])->toBeFalse()
        ->and(FrameText::line($frame, 'lower back', 'Well, doctor, it hurts in his lower back.')['matches'])->toBeTrue()
        ->and(FrameText::line($frame, 'lower back', 'I think that it hurts in his lower back.')['matches'])->toBeFalse()
        // A frame with a space before its mark is typography: the sentence has none.
        ->and(FrameText::fill('I work ___ .', 'from home'))->toBe('I work from home.')
        // No filler for a slot: nothing to put together, and a «___» never reaches the learner.
        ->and(FrameText::line($frame, null, 'It hurts.'))->toBe(['text' => 'It hurts.', 'matches' => false, 'glue' => '']);
});

// Canon v4.5 (FILLERS, NATURAL ORDER OF ONE VISIT): «a frame used in one exchange has exactly one in_dialogue filler;
// a frame used in two exchanges has two — one per exchange, and they are different», «no two exchanges ask the same
// thing». Catches marks that lie about what the dialogue says — a filler marked and never said, a filler said and not
// marked — a frame said twice with two marks counted as a breach, and the same filler said twice left uncounted or
// counted as a mark instead of a repeated exchange.
it('marks in the dialogue exactly the fillers it says, and counts the same filler said twice as a repeated exchange', function () {
    $clean = laPayload();
    $markedNotSaid = laPayload();
    $markedNotSaid['phrases'][5]['slot']['fillers'][2]['in_dialogue'] = true;
    $saidNotMarked = laPayload();
    $saidNotMarked['phrases'][5]['slot']['fillers'][1]['in_dialogue'] = false;
    $sameTwice = laPayload();
    $sameTwice['dialogue'][7]['messages'][0]['filler'] = 'an X-ray';
    $sameTwice['dialogue'][7]['messages'][0]['text_target'] = 'Do we need an X-ray?';
    $sameTwice['phrases'][5]['slot']['fillers'][1]['in_dialogue'] = false;

    expect(laCodesAt($clean, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe([])
        ->and(laCodesAt($clean, LessonCodes::EXCHANGE_REPEATS))->toBe([])
        ->and(laCodesAt($markedNotSaid, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe(['p6.f3'])
        ->and(laCodesAt($saidNotMarked, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe(['p6.f2'])
        ->and(laCodesAt($sameTwice, LessonCodes::EXCHANGE_REPEATS))->toBe(['x8'])
        ->and(laCodesAt($sameTwice, LessonCodes::FILLER_ONE_IN_DIALOGUE))->toBe([]);
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

    $served = LessonAssembly::serve($answer, '01M2SCENEFORSHUFFLE0000000');
    $again = LessonAssembly::serve($answer, '01M2SCENEFORSHUFFLE0000000');

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
    $terms = PlanTerm::fromLesson($sceneId, LessonAssembly::serve(laLesson(laPayload()), $sceneId->value), static fn (): PlanTermId => PlanTermId::generate());
    $byRef = [];
    foreach ($terms as $term) {
        $byRef[$term->ref()] = $term;
    }

    expect(count($terms))->toBe(14)
        ->and($byRef['p5']->kind())->toBe(TermKind::Phrase)
        ->and($byRef['p5']->textTarget())->toBe('He will rest at home.')
        ->and($byRef['p5']->textNative())->toBe('Он будет отдыхать дома.')
        ->and($byRef['p5']->pronunciationNative())->toBe('хи уил рэст эт хоум')
        // The line lends the phrase its key and example — with its glue: it is what was said.
        ->and($byRef['p5']->speakingKey())->toBe('will rest')
        ->and($byRef['p5']->exampleTarget())->toBe('Okay, he will rest at home.')
        ->and($byRef['p5']->frame()?->frameTarget)->toBe('He will rest ___.')
        ->and($byRef['p6']->textTarget())->toBe('Do we need an X-ray?')
        ->and(count($byRef['p6']->frame()->slot->fillers ?? []))->toBe(3)
        ->and($byRef['p4']->textTarget())->toBe('He doesn\'t have a fever.')
        ->and($byRef['v5']->usedIn())->toBe(['A5', 'A6'])
        ->and($byRef['v5']->exampleTarget())->toBe('It looks like a muscle strain, so he should rest and use a heating pad.');
});
