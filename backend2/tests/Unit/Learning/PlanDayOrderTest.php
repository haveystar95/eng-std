<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanDayOrder;
use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;

beforeEach(fn () => $this->order = new PlanDayOrder());

function card(string $id, string $kind, ?int $score = null, bool $role = false, ?string $shelf = null): PlanDayCard
{
    return new PlanDayCard($id, $kind, $role, $score, $shelf);
}

/** Every block, deliberately shuffled on the way in. */
function mixedDay(): array
{
    return [
        card('ask-hard', 'line', 14, shelf: 'ask'),
        card('hear', 'line', 1, role: true, shelf: 'hear'),
        card('word-easy', 'word', 2, shelf: 'words'),
        card('chunk-hard', 'chunk', 11, shelf: 'chunks'),
        card('say-easy', 'line', 9, shelf: 'say'),
        card('word-hard', 'word', 4, shelf: 'words'),
        card('chunk-easy', 'chunk', 3, shelf: 'chunks'),
        card('say-hard', 'line', 12, shelf: 'say'),
        card('ask-easy', 'line', 6, shelf: 'ask'),
    ];
}

it('lays the day out in the order of the canon: pieces, what they say, your answers, your questions', function () {
    // Канон §11, and the shelf is what states it — «Ты ответишь» and «Ты спросишь» are both `line`
    // and are two sections of the sitting.
    expect($this->order->order(mixedDay(), PlanLevel::Zero))->toBe([
        'word-easy', 'word-hard',
        'chunk-easy', 'chunk-hard',
        'hear',
        'say-easy', 'say-hard',
        'ask-easy', 'ask-hard',
    ]);
});

it('lays it out the same way at every level — the level does not move the blocks', function () {
    // Until PLAN-FIX-4 `conversational` and up inverted this, on the reading that the words inside a
    // reply are «recognised on the way past». No card does that recognising, so the day opened on a
    // fifteen-word sentence whose pieces had not been met (owner's screen, 01.09).
    $expected = $this->order->order(mixedDay(), PlanLevel::Zero);

    foreach (PlanLevel::cases() as $level) {
        expect($this->order->order(mixedDay(), $level))->toBe($expected);
    }
});

it('puts the interlocutor’s line BEFORE the learner’s, however hard it is', function () {
    // The scene's own order (канон §11), and the reverse of what this test asserted until SIT-1:
    // you hear what is said to you and then you answer it. Score 14 against 1 — inside a block the
    // role line would sort last, and it is not inside that block.
    $cards = [card('mine', 'line', 1, shelf: 'say'), card('role', 'line', 14, role: true, shelf: 'hear')];

    expect($this->order->order($cards, PlanLevel::Fluent))->toBe(['role', 'mine']);
});

it('still tells the two apart with no shelf at all, the way a pre-v0.4 day is drawn', function () {
    // A day written before shelves existed: `speaker` is the only fact there is, and it puts the
    // interlocutor's line where the shelf would have.
    $cards = [card('mine', 'line', 1), card('role', 'line', 14, role: true)];

    expect($this->order->order($cards, PlanLevel::Fluent))->toBe(['role', 'mine']);
});

it('treats an unscored term as easy, not as last', function () {
    // Every term written before plans existed has no score. Sinking them all to the bottom would
    // reorder a re-used vocabulary for a reason about our data, not about the language.
    $cards = [card('scored', 'word', 8), card('unscored', 'word', null)];

    expect($this->order->order($cards, PlanLevel::Basic))->toBe(['unscored', 'scored']);
});

it('rides an unknown kind with the words, like every other reader does', function () {
    $cards = [card('line', 'line', 5), card('legacy', 'mystery', 5)];

    expect($this->order->order($cards, PlanLevel::Basic))->toBe(['legacy', 'line']);
});

it('keeps the model own order for terms of equal difficulty', function () {
    $cards = [card('a', 'line', 6, shelf: 'say'), card('b', 'line', 6, shelf: 'say'), card('c', 'line', 6, shelf: 'say')];

    expect($this->order->order($cards, PlanLevel::Fluent))->toBe(['a', 'b', 'c']);
});

it('answers an empty day with an empty order', function () {
    expect($this->order->order([], PlanLevel::Basic))->toBe([]);
});
