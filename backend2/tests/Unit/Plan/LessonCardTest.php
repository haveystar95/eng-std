<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * ONE CARD BY ITS ADDRESS (`lesson_card_repair.v1.5`, наряд GEN-4): which addresses are cards a repair can take — of the
 * skeleton a frame, a partner line, a word; of the dialogue an exchange, its check, a listening question — which findings
 * belong to a card, and what putting a repaired card back changes: that card, what the server holds of it kept, and the
 * lines of the dialogue that say it — nothing else.
 */

/** @return array{0: Skeleton, 1: Dialogue} the fake's clean day, its two stages as the model writes them */
function lcStages(): array
{
    $request = FakePlanModel::lessonRequest('Приём');
    $parser = new LessonParser;
    $skeleton = $parser->skeleton(FakePlanModel::skeletonPayload($request));

    return [$skeleton, $parser->dialogue(FakePlanModel::dialoguePayload(new DialogueRequest($request, $skeleton)))];
}

it('takes a frame, a filler of it, a partner line, a word, an exchange, a message of it, a check and a listening question — and nothing else', function () {
    [$skeleton, $dialogue] = lcStages();
    $covers = static fn (string $card, string $at): bool => LessonCard::at($card)?->covers(new LessonViolation('x', $at, '')) ?? false;

    expect(LessonCard::at('p3')?->kind)->toBe(LessonCard::FRAME)
        ->and(LessonCard::at('p3.f2')?->address)->toBe('p3')
        ->and(LessonCard::at('a3')?->kind)->toBe(LessonCard::PARTNER_LINE)
        ->and(LessonCard::at('v4')?->kind)->toBe(LessonCard::TERM)
        ->and(LessonCard::at('x3')?->kind)->toBe(LessonCard::EXCHANGE)
        ->and([LessonCard::at('A3')?->address, LessonCard::at('B3')?->address])->toBe(['x3', 'x3'])
        ->and(LessonCard::at('x3.check')?->kind)->toBe(LessonCard::CHECK)
        ->and(LessonCard::at('L2')?->kind)->toBe(LessonCard::LISTENING)
        ->and([LessonCard::at('L0'), LessonCard::at('skeleton'), LessonCard::at('dialogue'), LessonCard::at('p')])->toBe([null, null, null, null])
        ->and(array_map(static fn (string $at): bool => LessonCard::at($at)?->ofSkeleton() ?? false, ['p3', 'a3', 'v4', 'x3', 'x3.check', 'L2']))
        ->toBe([true, true, true, false, false, false])
        // A frame holds its fillers; an exchange its two messages, not its check.
        ->and([$covers('p3', 'p3.f1'), $covers('p3', 'p30'), $covers('v4', 'v4'), $covers('v4', 'v40')])->toBe([true, false, true, false])
        ->and(array_map(static fn (string $at): bool => $covers('x3', $at), ['x3', 'A3', 'B3', 'x3.check', 'B30', 'x4']))->toBe([true, true, true, false, false, false])
        // Each card read out of its stage, in that stage's own shape.
        ->and(LessonCard::at('v2')?->of($skeleton, null)['term_target'] ?? null)->toBe('sharp')
        ->and(LessonCard::at('a3')?->of($skeleton, null)['text_target'] ?? null)->toBe('Is the pain sudden, or more like a slow ache?')
        ->and(LessonCard::at('p3')?->of($skeleton, null)['must_say'] ?? null)->toBe([3])
        ->and(LessonCard::at('x3')?->of($skeleton, $dialogue)['partner_line'] ?? null)->toBe('a3')
        ->and(LessonCard::at('x4.check')?->of($skeleton, $dialogue)['text_target'] ?? null)->toBe('What symptom does the doctor ask about?')
        ->and(LessonCard::at('L2')?->of($skeleton, $dialogue)['text_native'] ?? null)->toBe('Что врач советует делать дома?')
        ->and(LessonCard::at('x3')?->of($skeleton, null))->toBeNull()
        ->and(LessonCard::at('p9')?->of($skeleton, $dialogue))->toBeNull();
});

// v1.5: «a frame (card kind "frame"): keep its id, kind and must_say». Catches a frame put back under the id or with the items
// the model wrote, and a frame repaired under dialogue lines that still say the old frame — the served line and the phrase
// card would teach two different sentences — or a line that loses its closing mark to a frame written without one.
it('puts a repaired frame back with its id, kind and must_say, and says the dialogue\'s lines with the new frame, glue and all', function () {
    [$skeleton, $dialogue] = lcStages();
    $card = LessonCard::at('p5');
    $raw = $card?->of($skeleton, $dialogue) ?? [];
    $raw = [...$raw, 'id' => 'p9', 'kind' => 'ask', 'must_say' => [9], 'frame_target' => 'He will stay ___.'];
    $parser = new LessonParser;
    [$fixed, $said] = $card->replace($skeleton, $dialogue, $parser->card(LessonCard::FRAME, $raw));
    [, $unmarked] = $card->replace($skeleton, $dialogue, $parser->card(LessonCard::FRAME, [...$raw, 'frame_target' => 'He will stay ___']));

    expect($fixed->frame('p5')?->phrase->frameTarget)->toBe('He will stay ___.')
        ->and($fixed->frame('p5')?->mustSay)->toBe([5])
        ->and($fixed->frame('p5')?->phrase->kind->value)->toBe('answer')
        ->and($fixed->frame('p9'))->toBeNull()
        ->and($said?->exchange(5)?->exchange->learner()?->textTarget)->toBe('Okay, he will stay at home.')
        ->and($unmarked?->exchange(5)?->exchange->learner()?->textTarget)->toBe('Okay, he will stay at home.')
        // Nothing else moved.
        ->and($said?->exchange(1)?->toArray())->toBe($dialogue->exchange(1)?->toArray())
        ->and($fixed->partnerLines)->toEqual($skeleton->partnerLines)
        ->and($fixed->vocabulary)->toEqual($skeleton->vocabulary);
});

// v1.5: «a partner line (card kind "partner_line"): keep its id, must_understand, kind and pairs_with»; наряд GEN-4, 3.9:
// «после починки partner_line код заменяет реплику A в её обмене». Catches a partner line put back with the item or the
// pairing the model wrote, and a repaired line the dialogue still says the old way — `partner.changed` on a day that is right.
it('puts a repaired partner line back under its id, item and pairing, and says it anew in the exchange that carries it', function () {
    [$skeleton, $dialogue] = lcStages();
    $card = LessonCard::at('a3');
    $raw = [...($card?->of($skeleton, $dialogue) ?? []), 'id' => 'a9', 'must_understand' => 1, 'pairs_with' => [1], 'kind' => 'statement',
        'text_target' => 'Is the pain sudden, or slow?', 'text_native' => 'Боль резкая или медленная?'];
    [$fixed, $said] = $card->replace($skeleton, $dialogue, (new LessonParser)->card(LessonCard::PARTNER_LINE, $raw));
    $line = $fixed->partnerLine('a3');

    expect([$line?->textTarget, $line?->textNative])->toBe(['Is the pain sudden, or slow?', 'Боль резкая или медленная?'])
        ->and([$line?->mustUnderstand, $line?->pairsWith, $line?->kind])->toBe([$skeleton->partnerLine('a3')?->mustUnderstand, [3], 'question'])
        ->and($fixed->partnerLine('a9'))->toBeNull()
        ->and($said?->exchange(3)?->exchange->partner()?->textTarget)->toBe('Is the pain sudden, or slow?')
        ->and($said?->exchange(3)?->exchange->partner()?->textNative)->toBe('Боль резкая или медленная?')
        ->and($said?->exchange(3)?->exchange->learner()?->toArray())->toBe($dialogue->exchange(3)?->exchange->learner()?->toArray())
        ->and($said?->exchange(4)?->toArray())->toBe($dialogue->exchange(4)?->toArray())
        // Before the dialogue exists, only the skeleton moves.
        ->and($card->replace($skeleton, null, (new LessonParser)->card(LessonCard::PARTNER_LINE, $raw))[1])->toBeNull();
});

// v1.5: «an exchange (card kind "exchange"): keep its step, kind, initiator, partner_line and must_understand». Catches an
// exchange put back at the step the model wrote, or re-pointed to another line of the skeleton.
it('puts a repaired exchange back at its step, with its kind, opener, partner line and item', function () {
    [$skeleton, $dialogue] = lcStages();
    $card = LessonCard::at('B3');
    $raw = $card?->of($skeleton, $dialogue) ?? [];
    $raw = [...$raw, 'step' => 9, 'kind' => 'ask', 'initiator' => 'B', 'partner_line' => 'a1', 'must_understand' => 1];
    $raw['messages'][1]['speaking_key'] = 'The pain is';
    [$fixed, $said] = $card->replace($skeleton, $dialogue, (new LessonParser)->card(LessonCard::EXCHANGE, $raw));
    $exchange = $said?->exchange(3);

    expect([$exchange?->step(), $exchange?->exchange->kind->value, $exchange?->exchange->initiator, $exchange?->partnerLine, $exchange?->mustUnderstand])
        ->toBe([3, 'answer', 'A', 'a3', $dialogue->exchange(3)?->mustUnderstand])
        ->and($exchange?->exchange->learner()?->speakingKey)->toBe('The pain is')
        ->and($said?->exchange(9))->toBeNull()
        ->and($said?->exchange(2)?->toArray())->toBe($dialogue->exchange(2)?->toArray())
        ->and($fixed)->toBe($skeleton);
});

it('puts a repaired check or listening question back in its place and nowhere else', function () {
    [$skeleton, $dialogue] = lcStages();
    $parser = new LessonParser;
    $check = LessonCard::at('x4.check')?->of($skeleton, $dialogue) ?? [];
    $check['text_target'] = 'Which symptom is the doctor asking about?';
    $question = LessonCard::at('L3')?->of($skeleton, $dialogue) ?? [];
    $question['text_native'] = 'Когда стоит прийти ещё раз?';

    [, $said] = LessonCard::at('x4.check')->replace($skeleton, $dialogue, $parser->card(LessonCard::CHECK, $check));
    [, $said] = LessonCard::at('L3')->replace($skeleton, $said, $parser->card(LessonCard::LISTENING, $question));

    expect($said?->exchange(4)?->exchange->check->textTarget)->toBe('Which symptom is the doctor asking about?')
        ->and($said?->exchange(4)?->exchange->messages)->toEqual($dialogue->exchange(4)?->exchange->messages)
        ->and($said?->listening[2]->textNative)->toBe('Когда стоит прийти ещё раз?')
        ->and($said?->listening[0])->toEqual($dialogue->listening[0])
        ->and($said?->exchange(3)?->toArray())->toBe($dialogue->exchange(3)?->toArray());
});

// P2R v1.2 (наряд GEN-3), v1.5: «vocabulary item (card kind "term"): keep its id». Catches a repaired word put in under the id
// the model wrote (two v7 in a day, v4 gone), at another place of the list, or over another word.
it('puts a repaired word back under its own id, at its place, and nowhere else', function () {
    [$skeleton, $dialogue] = lcStages();
    $word = (new LessonParser)->card(LessonCard::TERM, [
        'id' => 'v7', 'term_target' => 'rest', 'translation_native' => 'отдых', 'pronunciation_native' => 'рэст',
        'definition_target' => 'time to relax', 'kind' => 'word', 'image_prompt' => null, 'used_in' => ['p5'],
    ]);

    [$fixed, $said] = LessonCard::at('v4')->replace($skeleton, $dialogue, $word);

    expect(array_map(static fn ($v): string => "{$v->id}:{$v->termTarget}", $fixed->vocabulary))
        ->toBe(['v1:lower back', 'v2:sharp', 'v3:fever', 'v4:rest', 'v5:heating pad', 'v6:X-ray', 'v7:follow-up appointment', 'v8:sick note'])
        ->and($fixed->frames)->toEqual($skeleton->frames)
        ->and($said)->toBe($dialogue);
});
