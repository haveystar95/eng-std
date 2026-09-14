<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\WordUsage;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/** EVERYTHING A DAY SAYS, WHOSE VOICE IT IS, AND THE LINE A WORD IS SAID IN — off a served `lesson_day.v4.4` lesson. */

function slLesson(?Closure $edit = null): Lesson
{
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8));

    return LessonAssembly::serve((new LessonParser)->parse($edit === null ? $payload : $edit($payload)), 'scene');
}

it('names every line of the dialogue by its exchange and speaker, in the order they are said', function () {
    $lines = SpokenLines::dialogue(slLesson());

    expect(array_column(array_slice($lines, 0, 4), 'ref'))->toBe(['x1', 'x1b', 'x2', 'x2b'])
        ->and($lines[0]['speaker'])->toBe(Speaker::Partner)
        // The rescue and the asks are opened by the learner: their line comes first and keeps its own name.
        ->and(array_column(array_slice($lines, 10, 2), 'ref'))->toBe(['x6b', 'x6'])
        // What is voiced is the served line — the frame said with its filler.
        ->and($lines[9]['text'])->toBe('Okay, he will rest at home.')
        ->and($lines)->toHaveCount(16);
});

it('gives the partner\'s line the partner\'s voice and everything else the learner\'s, the learner always the other gender', function () {
    expect(SpokenLines::speakerOf('x3'))->toBe(Speaker::Partner)
        ->and(SpokenLines::speakerOf('x3b'))->toBe(Speaker::Learner)
        ->and(SpokenLines::speakerOf('p2'))->toBe(Speaker::Learner)
        ->and(SpokenLines::speakerOf('v5'))->toBe(Speaker::Learner)
        ->and(VoiceCast::of(VoiceGender::Male)->learner())->toBe(VoiceGender::Female)
        ->and(VoiceCast::of(null)->partner)->toBe(VoiceGender::Female)
        ->and(slLesson()->roleGender)->toBe(VoiceGender::Female);
});

// 23-0e: «В разговоре» is the line the lesson says the word in (`used_in`), and the word's place in it in characters.
// Catches a highlight counted in bytes, a line picked that does not contain the word, and a filler the dialogue never
// says shown with a line.
it('finds the line a word is said in by where the lesson says it, and places the word in it in characters', function () {
    $lesson = slLesson();
    $pad = WordUsage::of($lesson, 'v5', 'heating pad');
    $xray = WordUsage::of($lesson, 'v6', 'X-ray');

    expect(Words::spanOfTerm('painkiller', 'I’ve got a ‘painkiller’ here'))->toBe([12, 10])
        ->and($pad['speaker'])->toBe(Speaker::Partner)
        ->and($pad['step'])->toBe(5)
        ->and(mb_substr($pad['text'], $pad['offset'], $pad['length']))->toBe('heating pad')
        // `used_in: [p6, A7]` — the learner's line on frame p6 comes first.
        ->and($xray['speaker'])->toBe(Speaker::Learner)
        ->and($xray['text'])->toBe('Do we need an X-ray?')
        ->and(WordUsage::of($lesson, 'v8', 'sick note'))->toBeNull();

    // A place named wrongly falls back to the first line of the visit that says the word.
    $moved = WordUsage::of(slLesson(static function (array $p): array {
        $p['vocabulary'][2]['used_in'] = ['A2'];

        return $p;
    }), 'v3', 'fever');
    expect($moved['step'])->toBe(4)->and($moved['speaker'])->toBe(Speaker::Partner);
});
