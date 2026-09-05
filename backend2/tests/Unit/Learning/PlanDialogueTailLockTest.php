<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanDialogueChain;
use App\Modules\Learning\Domain\ValueObject\PlanDialogueMove;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * ХВОСТ — НЕ РАЗГОВОР: замок чередования цепочки (наряд DAY-FIX-2, Ч.5.5).
 *
 * Живой прогон 05.09: три своих вопроса подряд пузырями в ленте, без собеседника. Это был выведенный
 * фолбэк — реплик роли меньше, чем своих, и остаток своих реплик выходил в цепочку сам по себе. Свой
 * ход без реплики перед ним в разговоре не стоит; он остаётся на полке и раздаётся отдельной
 * карточкой ПОСЛЕ разговора.
 */
beforeEach(fn () => $this->chain = new PlanDialogueChain());

function tailTrace(array $moves): array
{
    return array_map(static fn (PlanDialogueMove $m): string => $m->turn . ':' . $m->termId, $moves);
}

it('never emits a learner`s turn that no role turn stands directly before — derived chain', function () {
    $cards = [
        new SituationalCandidate('h1', 'hear', 's1', 'Hi, thanks for joining.'),
        new SituationalCandidate('s1', 'say', 's1', 'Sure, happy to.'),
        new SituationalCandidate('a1', 'ask', 's2', 'Can you hear me clearly?'),
        new SituationalCandidate('a2', 'ask', 's3', 'Would you like a quick overview?'),
        new SituationalCandidate('a3', 'ask', 's4', 'Should I focus on my current role?'),
    ];

    // One question, one exchange. The three questions have nobody to answer and are the TAIL.
    expect(tailTrace($this->chain->for(null, $cards)))->toBe(['role:h1', 'you:s1']);
});

it('never emits one from a stored chain either, after a missing card breaks the alternation', function () {
    $cards = [
        new SituationalCandidate('h1', 'hear', 's1', 'A'),
        new SituationalCandidate('s1', 'say', 's1', 'B'),
        new SituationalCandidate('s2', 'say', 's2', 'D'),
    ];
    $stored = [
        ['turn' => 'role', 'term_id' => 'h1'],
        ['turn' => 'you', 'term_id' => 's1'],
        ['turn' => 'role', 'term_id' => 'h-gone'],   // the day lost this card
        ['turn' => 'you', 'term_id' => 's2'],
    ];

    expect(tailTrace($this->chain->for($stored, $cards)))->toBe(['role:h1', 'you:s1']);
});

it('keeps a trailing role line — the other side may close the scene', function () {
    $cards = [
        new SituationalCandidate('h1', 'hear', 's1', 'A'),
        new SituationalCandidate('s1', 'say', 's1', 'B'),
        new SituationalCandidate('h2', 'hear', 's2', 'Goodbye.'),
    ];

    expect(tailTrace($this->chain->for(null, $cards)))->toBe(['role:h1', 'you:s1', 'role:h2']);
});

it('carries the pair type of a v0.6 chain and says nothing for an older one', function () {
    $cards = [
        new SituationalCandidate('h1', 'hear', 's1', 'A'),
        new SituationalCandidate('a1', 'ask', 's1', 'Q?'),
    ];
    $stored = [
        ['turn' => 'role', 'term_id' => 'h1', 'pair' => 'ask'],
        ['turn' => 'you', 'term_id' => 'a1', 'pair' => 'ask'],
    ];

    $moves = $this->chain->for($stored, $cards);
    expect(array_map(static fn (PlanDialogueMove $m): ?string => $m->pair, $moves))->toBe(['ask', 'ask'])
        ->and(array_map(static fn (PlanDialogueMove $m): ?string => $m->pair, $this->chain->for(null, $cards)))->toBe([null, null]);
});
