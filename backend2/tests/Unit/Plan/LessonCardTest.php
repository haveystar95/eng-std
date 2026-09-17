<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * P2R — ONE CARD BY ITS ADDRESS (наряды GEN-2a, GEN-2b, GEN-3): which addresses are cards a repair can take, which findings
 * belong to a card, and what putting a repaired card back changes — that card, and for a frame the lines that
 * stand on it, nothing else.
 */

function lcAnswer(): Lesson
{
    return (new LessonParser)->parse(FakePlanModel::lessonPayload(new LessonRequest('Приём', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays)));
}

it('takes a frame, a filler of it, a whole exchange, a learner line, a check, a listening question and a word — and nothing else', function () {
    expect(LessonCard::at('p3')?->kind)->toBe(LessonCard::FRAME)
        ->and(LessonCard::at('p3.f2')?->address)->toBe('p3')
        ->and(LessonCard::at('x3')?->kind)->toBe(LessonCard::EXCHANGE)
        ->and(LessonCard::at('B3')?->kind)->toBe(LessonCard::LINE)
        ->and(LessonCard::at('x3.check')?->kind)->toBe(LessonCard::CHECK)
        ->and(LessonCard::at('L2')?->kind)->toBe(LessonCard::LISTENING)
        ->and(LessonCard::at('A3'))->toBeNull()
        ->and(LessonCard::at('v4')?->kind)->toBe(LessonCard::TERM)
        ->and(LessonCard::at('v4')?->covers(new LessonViolation('vocab.known_repeat', 'v4', '')))->toBeTrue()
        ->and(LessonCard::at('v4')?->covers(new LessonViolation('vocab.known_repeat', 'v40', '')))->toBeFalse()
        ->and(LessonCard::at('v2')?->of(lcAnswer())['term_target'] ?? null)->toBe('sharp')
        ->and(LessonCard::at('lesson'))->toBeNull()
        ->and(LessonCard::at('p3')?->covers(new LessonViolation('filler.count', 'p3.f1', '')))->toBeTrue()
        ->and(LessonCard::at('p3')?->covers(new LessonViolation('filler.count', 'p30', '')))->toBeFalse()
        // An exchange card holds its two messages and its check, and nothing of another exchange.
        ->and(array_map(static fn (string $at): bool => LessonCard::at('x3')?->covers(new LessonViolation('x', $at, '')) ?? false, ['x3', 'A3', 'B3', 'x3.check', 'B30', 'x4']))
        ->toBe([true, true, true, true, false, false])
        ->and(LessonCard::at('B3')?->of(lcAnswer())['text_target'] ?? null)->toBe('The pain is sharp when he bends.')
        ->and(LessonCard::at('x3')?->of(lcAnswer())['kind'] ?? null)->toBe('answer');
});

// Canon GEN-2b: «P2R получает новый вид карточки exchange и поле frame_update — сборка применяет его атомарно (обмен +
// каркас)». Catches an exchange put in without the frame it came with (its new filler never marked), a frame put in
// without its exchange, and a pair that does not fit — a frame the repaired line does not stand on — half-applied.
it('puts a repaired exchange back together with the frame it came with, or nothing at all', function () {
    $answer = lcAnswer();
    $parser = new LessonParser;
    $card = LessonCard::at('x8');
    $exchange = $card?->of($answer);
    $exchange['messages'][0]['filler'] = 'a sick note';
    $exchange['messages'][0]['text_target'] = 'Do we need a sick note?';
    $frame = LessonCard::at('p6')?->of($answer);
    $frame['slot']['fillers'][2]['in_dialogue'] = true;

    $repaired = $card->replaceExchange($answer, $parser->card(LessonCard::EXCHANGE, $exchange), $parser->frameUpdate($frame));
    $alien = $frame;
    $alien['id'] = 'p5';

    expect(LessonAssembly::fillerOf($repaired ?? throw new LogicException, $repaired->exchange(8)?->learner() ?? throw new LogicException)?->target)->toBe('a sick note')
        ->and(array_map(static fn ($f): bool => $f->inDialogue, $repaired?->phrase('p6')->slot->fillers ?? []))->toBe([true, true, true])
        ->and($repaired?->exchange(7)?->toArray())->toBe($answer->exchange(7)?->toArray())
        ->and($card->replaceExchange($answer, $parser->card(LessonCard::EXCHANGE, $exchange), $parser->frameUpdate($alien)))->toBeNull()
        ->and($card->replaceExchange($answer, $parser->card(LessonCard::EXCHANGE, $exchange), null)?->phrase('p6'))->toEqual($answer->phrase('p6'));
});

// A repaired frame keeps the dialogue true to it. Catches a frame repaired under lines that still say the old frame
// — the served line and the phrase card would teach two different sentences — and a line that loses its closing mark
// to a frame written without one.
it('puts a repaired frame back and says its dialogue lines with the new frame, glue and all', function () {
    $answer = lcAnswer();
    $card = LessonCard::at('p5');
    $raw = $card?->of($answer);
    $raw['frame_target'] = 'He will stay ___.';
    $repaired = $card->replace($answer, (new LessonParser)->card(LessonCard::FRAME, $raw));
    $unmarked = $raw;
    $unmarked['frame_target'] = 'He will stay ___';

    expect($repaired->phrase('p5')?->frameTarget)->toBe('He will stay ___.')
        ->and($repaired->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will stay at home.')
        ->and(LessonAssembly::serve($repaired, 's', lessonPacks()->for('en'))->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will stay at home.')
        ->and($card->replace($answer, (new LessonParser)->card(LessonCard::FRAME, $unmarked))->exchange(5)?->learner()?->textTarget)->toBe('Okay, he will stay at home.')
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

// Наряд GEN-3, P2R v1.2: «vocabulary item (card kind "term"): keep its id. Replace the item with a different word». Catches a
// repaired word put in under the id the model wrote (two v2 in a day, v4 gone), at another place of the list, or over
// another word.
it('puts a repaired word back under its own id, at its place, and nowhere else', function () {
    $answer = lcAnswer();
    $card = LessonCard::at('v4');
    $word = (new LessonParser)->card(LessonCard::TERM, [
        'id' => 'v7', 'term_target' => 'rest', 'translation_native' => 'отдых', 'pronunciation_native' => 'рэст',
        'definition_target' => 'time to relax', 'kind' => 'word', 'image_prompt' => null, 'used_in' => ['p5'],
    ]);

    $repaired = $card?->replace($answer, $word);

    expect(array_map(static fn ($v): string => "{$v->id}:{$v->termTarget}", $repaired?->vocabulary ?? []))
        ->toBe(['v1:lower back', 'v2:sharp', 'v3:fever', 'v4:rest', 'v5:heating pad', 'v6:X-ray', 'v7:follow-up appointment', 'v8:sick note'])
        ->and($repaired?->exchanges)->toEqual($answer->exchanges)
        ->and($repaired?->phrases)->toEqual($answer->phrases);
});
