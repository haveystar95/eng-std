<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanDialogueChain;
use App\Modules\Learning\Domain\ValueObject\PlanDialogueMove;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * THE ORDER A SCENE IS SPOKEN IN — one answer for a day that carries its own chain and for every
 * day written before P2 v0.5 (наряд DAY-2, Ч.1.3).
 *
 * The second half is the one that has to hold: there are plans on the phone right now whose days
 * were written by v0.4, and «ни один существующий план не ломается» is a promise about them.
 */
beforeEach(fn () => $this->chain = new PlanDialogueChain());

/** A day's cards, in the order a caller happens to hand them over — which must not matter. */
function dialogueCards(): array
{
    return [
        new SituationalCandidate('t-say-1', 'say', 's1.1', 'My background is in backend development.'),
        new SituationalCandidate('t-hear-1', 'hear', 's1.1', 'Could you tell me about your background?'),
        new SituationalCandidate('t-word-1', 'words', 's1.1', 'background'),
        new SituationalCandidate('t-hear-2', 'hear', 's1.2', 'What are you working on now?'),
        new SituationalCandidate('t-say-2', 'say', 's1.2', "I'm building a learning app."),
        new SituationalCandidate('t-ask-1', 'ask', 's1.2', 'Could you repeat the question?'),
    ];
}

/** @param list<PlanDialogueMove> $moves */
function dialogueTrace(array $moves): array
{
    return array_map(static fn (PlanDialogueMove $m): string => $m->turn . ':' . $m->termId, $moves);
}

// ── the day that brought its own chain ───────────────────────────────────────────────────────

it('plays a stored chain exactly as the day stored it', function () {
    $stored = [
        ['turn' => 'role', 'term_id' => 't-hear-2'],
        ['turn' => 'you', 'term_id' => 't-say-2'],
        ['turn' => 'role', 'term_id' => 't-hear-1'],
        ['turn' => 'you', 'term_id' => 't-say-1'],
    ];

    expect(dialogueTrace($this->chain->for($stored, dialogueCards())))
        ->toBe(['role:t-hear-2', 'you:t-say-2', 'role:t-hear-1', 'you:t-say-1']);
});

it('drops a stored turn whose card the day no longer has', function () {
    // A conversation with a silence in it is worse than a shorter one.
    $stored = [
        ['turn' => 'role', 'term_id' => 't-hear-1'],
        ['turn' => 'you', 'term_id' => 't-gone'],
        ['turn' => 'you', 'term_id' => 't-say-1'],
    ];

    expect(dialogueTrace($this->chain->for($stored, dialogueCards())))
        ->toBe(['role:t-hear-1', 'you:t-say-1']);
});

it('drops a stored turn that calls a reply the interlocutor’s — Д-8 from the other side', function () {
    $stored = [['turn' => 'role', 'term_id' => 't-say-1']];

    expect($this->chain->for($stored, dialogueCards()))->toBe([]);
});

// ── the day written before v0.5 ──────────────────────────────────────────────────────────────

it('pairs the shelves by skill_ref when the day has no chain of its own', function () {
    // The pairing the situational card already made: the «Тебе скажут» line serving the same
    // ability as the reply IS the question that reply answers.
    expect(dialogueTrace($this->chain->for(null, dialogueCards())))
        ->toBe([
            'role:t-hear-1', 'you:t-say-1',
            'role:t-hear-2', 'you:t-say-2',
            // «Ты спросишь» after «Ты ответишь» (канон §11), and its own ability's line is already
            // spent, so the question rides the next unused one — there is none, so it stands alone.
            'you:t-ask-1',
        ]);
});

it('answers the same for a chain stored empty as for one never stored', function () {
    expect(dialogueTrace($this->chain->for([], dialogueCards())))
        ->toBe(dialogueTrace($this->chain->for(null, dialogueCards())));
});

it('puts every reply of the scene in the chain, and every role line too', function () {
    $moves = $this->chain->for(null, dialogueCards());
    $inChain = array_map(static fn (PlanDialogueMove $m): string => $m->termId, $moves);

    foreach (['t-say-1', 't-say-2', 't-ask-1', 't-hear-1', 't-hear-2'] as $termId) {
        expect($inChain)->toContain($termId);
    }

    // …and the word is NOT: «слова и связки» is its own part of the sitting, and a conversation
    // made of vocabulary cards is not a conversation.
    expect($inChain)->not->toContain('t-word-1')
        // Nothing is spoken twice: a role line the learner hears again is the app repeating itself.
        ->and($inChain)->toBe(array_values(array_unique($inChain)));
});

it('falls back to the next unpaired line when a reply’s own ability has none', function () {
    $cards = [
        new SituationalCandidate('t-hear-1', 'hear', 's1.9', 'Anything else?'),
        new SituationalCandidate('t-say-1', 'say', 's1.1', 'No, that is all.'),
    ];

    // The abilities do not match, and the reply still needs somebody to have said something.
    expect(dialogueTrace($this->chain->for(null, $cards)))
        ->toBe(['role:t-hear-1', 'you:t-say-1']);
});

it('ends on the lines nobody answered rather than losing them', function () {
    $cards = [
        new SituationalCandidate('t-hear-1', 'hear', 's1.1', 'Hi, thanks for joining today.'),
        new SituationalCandidate('t-hear-2', 'hear', 's1.2', "Great, that's helpful. Thank you."),
        new SituationalCandidate('t-say-1', 'say', 's1.1', 'Sure, happy to.'),
    ];

    expect(dialogueTrace($this->chain->for(null, $cards)))
        ->toBe(['role:t-hear-1', 'you:t-say-1', 'role:t-hear-2']);
});

it('is stable: the same day gives the same conversation whatever order the cards arrive in', function () {
    $forwards = dialogueTrace($this->chain->for(null, dialogueCards()));
    $backwards = dialogueTrace($this->chain->for(null, array_reverse(dialogueCards())));

    expect($backwards)->toBe($forwards);
});

it('answers an empty day with no conversation at all', function () {
    expect($this->chain->for(null, []))->toBe([])
        ->and($this->chain->for([['turn' => 'role', 'term_id' => 't-hear-1']], []))->toBe([]);
});
