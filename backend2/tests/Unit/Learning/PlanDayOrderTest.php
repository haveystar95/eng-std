<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanDayOrder;
use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;

beforeEach(fn () => $this->order = new PlanDayOrder());

function card(string $id, bool $isLine, ?int $score): PlanDayCard
{
    return new PlanDayCard($id, $isLine, $score);
}

it('puts the words first for a learner who cannot yet assemble a reply', function () {
    $cards = [
        card('line-hard', true, 14),
        card('word-easy', false, 2),
        card('line-easy', true, 9),
        card('word-hard', false, 4),
    ];

    expect($this->order->order($cards, PlanLevel::Zero))
        ->toBe(['word-easy', 'word-hard', 'line-easy', 'line-hard'])
        ->and($this->order->order($cards, PlanLevel::Basic))
        ->toBe(['word-easy', 'word-hard', 'line-easy', 'line-hard']);
});

it('opens on the replies once the learner can hold a conversation', function () {
    $cards = [
        card('line-hard', true, 14),
        card('word-easy', false, 2),
        card('line-easy', true, 9),
        card('word-hard', false, 4),
    ];

    expect($this->order->order($cards, PlanLevel::Conversational))
        ->toBe(['line-easy', 'line-hard', 'word-easy', 'word-hard'])
        ->and($this->order->order($cards, PlanLevel::Fluent))
        ->toBe(['line-easy', 'line-hard', 'word-easy', 'word-hard']);
});

it('treats an unscored term as easy, not as last', function () {
    // Every term written before plans existed has no score. Sinking them all to the bottom would
    // reorder a re-used vocabulary for a reason about our data, not about the language.
    $cards = [card('scored', false, 8), card('unscored', false, null)];

    expect($this->order->order($cards, PlanLevel::Basic))->toBe(['unscored', 'scored']);
});

it('keeps the model own order for terms of equal difficulty', function () {
    $cards = [card('a', true, 6), card('b', true, 6), card('c', true, 6)];

    expect($this->order->order($cards, PlanLevel::Fluent))->toBe(['a', 'b', 'c']);
});

it('answers an empty day with an empty order', function () {
    expect($this->order->order([], PlanLevel::Basic))->toBe([]);
});
