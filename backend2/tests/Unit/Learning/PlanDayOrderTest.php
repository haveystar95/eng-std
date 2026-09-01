<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanDayOrder;
use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;

beforeEach(fn () => $this->order = new PlanDayOrder());

function card(string $id, string $kind, ?int $score = null, bool $role = false): PlanDayCard
{
    return new PlanDayCard($id, $kind, $role, $score);
}

/** Every block, deliberately shuffled on the way in. */
function mixedDay(): array
{
    return [
        card('line-hard', 'line', 14),
        card('role', 'line', 1, role: true),
        card('word-easy', 'word', 2),
        card('chunk-hard', 'chunk', 11),
        card('line-easy', 'line', 9),
        card('word-hard', 'word', 4),
        card('chunk-easy', 'chunk', 3),
    ];
}

it('lays the day out as pieces, connectors, replies, and the interlocutor last', function () {
    expect($this->order->order(mixedDay(), PlanLevel::Zero))->toBe([
        'word-easy', 'word-hard',
        'chunk-easy', 'chunk-hard',
        'line-easy', 'line-hard',
        'role',
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

it('puts the interlocutor’s line after the learner’s, however easy it is', function () {
    // Score 1 against 9: inside a block it would lead. It is not inside that block — it is the one
    // card of the day the learner will never say.
    $cards = [card('role', 'line', 1, role: true), card('mine', 'line', 9)];

    expect($this->order->order($cards, PlanLevel::Fluent))->toBe(['mine', 'role']);
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
    $cards = [card('a', 'line', 6), card('b', 'line', 6), card('c', 'line', 6)];

    expect($this->order->order($cards, PlanLevel::Fluent))->toBe(['a', 'b', 'c']);
});

it('answers an empty day with an empty order', function () {
    expect($this->order->order([], PlanLevel::Basic))->toBe([]);
});
