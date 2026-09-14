<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * P2R — ONE CARD BY ITS ADDRESS (наряд GEN-2a): which addresses are cards a repair can take, which findings
 * belong to a card, and what putting a repaired card back changes — that card, and for a frame the lines that
 * stand on it, nothing else.
 */

function lcAnswer(): Lesson
{
    return (new LessonParser)->parse(FakePlanModel::lessonPayload(new LessonRequest('Приём', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8)));
}

it('takes a frame, a filler of it, a learner line, a check and a listening question — and nothing else', function () {
    expect(LessonCard::at('p3')?->kind)->toBe(LessonCard::FRAME)
        ->and(LessonCard::at('p3.f2')?->address)->toBe('p3')
        ->and(LessonCard::at('B3')?->kind)->toBe(LessonCard::LINE)
        ->and(LessonCard::at('x3.check')?->kind)->toBe(LessonCard::CHECK)
        ->and(LessonCard::at('L2')?->kind)->toBe(LessonCard::LISTENING)
        ->and(LessonCard::at('A3'))->toBeNull()
        ->and(LessonCard::at('v4'))->toBeNull()
        ->and(LessonCard::at('lesson'))->toBeNull()
        ->and(LessonCard::at('x3'))->toBeNull()
        ->and(LessonCard::at('p3')?->covers(new LessonViolation('filler.count', 'p3.f1', '')))->toBeTrue()
        ->and(LessonCard::at('p3')?->covers(new LessonViolation('filler.count', 'p30', '')))->toBeFalse()
        ->and(LessonCard::at('B3')?->of(lcAnswer())['text_target'] ?? null)->toBe('The pain is sharp when he bends.');
});

// A repaired frame keeps the dialogue true to it. Catches a frame repaired under lines that still say the old frame
// — the served line and the phrase card would teach two different sentences.
it('puts a repaired frame back and says its dialogue lines with the new frame, glue and all', function () {
    $answer = lcAnswer();
    $card = LessonCard::at('p5');
    $raw = $card?->of($answer);
    $raw['frame_target'] = 'He will stay ___.';
    $repaired = $card->replace($answer, (new LessonParser)->card(LessonCard::FRAME, $raw));

    expect($repaired->phrase('p5')?->frameTarget)->toBe('He will stay ___.')
        ->and($repaired->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will stay at home.')
        ->and(LessonAssembly::serve($repaired, 's')->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will stay at home.')
        // Nothing else moved.
        ->and($repaired->exchange(1)?->toArray())->toBe($answer->exchange(1)?->toArray())
        ->and($repaired->listening)->toEqual($answer->listening);
});

it('puts a repaired line, check or listening question back in its place and nowhere else', function () {
    $answer = lcAnswer();
    $parser = new LessonParser;

    $line = LessonCard::at('B2')?->of($answer);
    $line['speaking_key'] = 'started';
    $check = LessonCard::at('x4.check')?->of($answer);
    $check['text_target'] = 'Which symptom is the doctor asking about?';
    $question = LessonCard::at('L3')?->of($answer);
    $question['text_native'] = 'Когда стоит прийти ещё раз?';

    $repaired = LessonCard::at('B2')->replace($answer, $parser->card(LessonCard::LINE, $line));
    $repaired = LessonCard::at('x4.check')->replace($repaired, $parser->card(LessonCard::CHECK, $check));
    $repaired = LessonCard::at('L3')->replace($repaired, $parser->card(LessonCard::LISTENING, $question));

    expect($repaired->exchange(2)?->learner()?->speakingKey)->toBe('started')
        ->and($repaired->exchange(2)?->partner()?->textTarget)->toBe($answer->exchange(2)?->partner()?->textTarget)
        ->and($repaired->exchange(4)?->check->textTarget)->toBe('Which symptom is the doctor asking about?')
        ->and($repaired->listening[2]->textNative)->toBe('Когда стоит прийти ещё раз?')
        ->and($repaired->listening[0])->toEqual($answer->listening[0])
        ->and($repaired->phrases)->toEqual($answer->phrases);
});
