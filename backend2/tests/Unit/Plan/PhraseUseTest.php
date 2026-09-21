<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;

/**
 * «ИСПОЛЬЗОВАЛ ФРАЗУ» — КАК ЧЕЛОВЕК (наряд BACK-TAILS-2 §2): a phrase is used when the learner said its meaning in its key
 * words; the order, the words around them and the form each is said in do not matter. One case per rule (а)–(д), the
 * owner's own «Weekdays works for me», and the proof the cards' channel is not touched.
 *
 * The targets are the owner's gym talk of 21.09 (plan `01M32DX8…`, «Ресепшен зала») and the fake «врач» — the texts a
 * talk's `targets[]` carries, the phrase as the dialogue says it.
 */
function puTarget(string $ref, string $text, string $scene = 'gym'): ConversationPhrase
{
    return new ConversationPhrase($scene, $ref, '', $text, '');
}

// (а) Both sides in one form, and the words that carry no content left out of both. CATCHES a rule that reads the
// contraction, the digit or the abbreviation as another word, or asks for the articles and the auxiliaries.
it('reads both sides in one form and leaves the unstressed words and the articles out of both', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');

    expect($use->said('I will return the locker key', "I'll return the locker key.", $en))->toBeTrue()
        ->and($use->said('give it every 6 hours', 'Give it every six hours.', $en))->toBeTrue()
        ->and($use->said('come at 3 pm', 'Come at 3 p.m.', $en))->toBeTrue()
        // «Do», «have», «a» are no key words of «Do you have a day pass?»: «you … day pass» is the phrase.
        ->and($use->said('you have day pass', 'Do you have a day pass?', $en))->toBeTrue()
        ->and($use->tally('you have day pass', 'Do you have a day pass?', $en))->toBe(['found' => 3, 'total' => 3]);
});

// (б) A key word is found by its base: the pack's regular endings and its table of irregular forms; a language without
// the table compares exactly. CATCHES a rule that asks for the word as written, a table that is not read, and a table
// borrowed by a language that has none.
it('finds a key word in another form by its base — and exactly in a language without the table', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $ru = lessonPacks()->for('ru');

    expect($use->said('weekday work for me that', 'That works for me on weekdays.', $en))->toBeTrue()
        ->and($use->said('I pay by card', 'I paid by card.', $en))->toBeTrue()
        ->and($use->said('she bought the tickets', 'She buys tickets.', $en))->toBeTrue()
        ->and($use->said('the doctor used the heating pad', 'The doctor uses a heating pad.', $en))->toBeTrue()
        ->and($use->bases('stopped', $en))->toContain('stop')
        ->and($use->bases('tries', $en))->toContain('try')
        ->and($use->bases('children', $en))->toContain('child')
        // Russian has no table: its words are compared as they are, in any order.
        ->and($ru->has('irregular_forms'))->toBeFalse()
        ->and($use->bases('нужна', $ru))->toBe(['нужна'])
        ->and($use->said('врач нужен мне', 'Мне нужен врач.', $ru))->toBeTrue()
        ->and($use->said('мне нужна врача', 'Мне нужен врач.', $ru))->toBeFalse();
});

// (в) Every key word in any order; one may go missing from four key words on, none below. CATCHES an order kept, a miss
// forgiven to a short phrase, and none forgiven to a long one.
it('says a phrase when every key word was heard in any order — one may go missing from four on', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');

    // «Can I pay by card?» — i, pay, card: three key words, all of them asked for.
    expect($use->tally('by card can I pay', 'Can I pay by card?', $en))->toBe(['found' => 3, 'total' => 3])
        ->and($use->said('by card can I pay', 'Can I pay by card?', $en))->toBeTrue()
        ->and($use->said('can I pay in cash', 'Can I pay by card?', $en))->toBeFalse()
        // «This is my first visit.» — this, my, first, visit: four, so «Yes it is my first visit» is the phrase.
        ->and($use->tally('Yes it is my first visit', 'This is my first visit.', $en))->toBe(['found' => 3, 'total' => 4])
        ->and($use->said('Yes it is my first visit', 'This is my first visit.', $en))->toBeTrue()
        // …and two of four are not.
        ->and($use->said('my visit', 'This is my first visit.', $en))->toBeFalse();
});

// The owner's own line, the one the наряд is for: «Weekdays works for me» against the target «That works for me on
// weekdays.» — «0 из 7» on the gym talk of 21.09 counted it as unsaid.
it('hears «Weekdays works for me» as «That works for me on weekdays.»', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');

    expect($use->tally('Weekdays works for me', 'That works for me on weekdays.', $en))->toBe(['found' => 3, 'total' => 4])
        ->and($use->said('Weekdays works for me', 'That works for me on weekdays.', $en))->toBeTrue()
        ->and($use->heardIn('Weekdays works for me', [puTarget('p4', 'That works for me on weekdays.')], [], $en))->toBe(['gym:p4']);
});

// (г) The model's word is the second support and never the first: a target the rule did not find counts when the role
// named it AND the heard line holds half of its key words. CATCHES the model's opinion ignored, and the model trusted
// alone — a phrase the role «heard» in a line that says nothing of it (the gym talk's first move named nothing, but a
// model that named every phrase on every move was the CONV-1 test of exactly this).
it('takes the model\'s word only for a line that holds half of the target\'s key words', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $monthly = puTarget('p2', 'What monthly memberships do you have?');

    // what, monthly, memberships, you — the move said two of them: not by the rule, by the rule and the model's word.
    expect($use->said('No I want to buy monthly membership', $monthly->textTarget, $en))->toBeFalse()
        ->and($use->heardIn('No I want to buy monthly membership', [$monthly], [], $en))->toBe([])
        ->and($use->heardIn('No I want to buy monthly membership', [$monthly], ['gym:p2'], $en))->toBe(['gym:p2'])
        // The model naming a phrase the line does not hold: nothing.
        ->and($use->heardIn('Hello I need daily training', [puTarget('p5', 'Where are the changing rooms?')], ['gym:p5'], $en))->toBe([])
        // …and naming another phrase than the one it holds half of credits neither.
        ->and($use->heardIn('No I want to buy monthly membership', [$monthly], ['gym:p1'], $en))->toBe([]);
});

// (д) A move is read for every target it is given — whatever scene it stands in — and for no other: the caller hands it
// the ones not said yet, so a phrase said stays said (ConversationApiTest walks that over HTTP). CATCHES a rule that only
// hears the phrases of one scene.
it('reads a move for every target it is given, of every scene, and hears each once', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $targets = [
        puTarget('p1', 'It hurts in his lower back.', 'reception'),
        puTarget('p3', 'The pain is sharp when he bends.', 'doctor'),
        puTarget('p6', 'Do we need an X-ray?', 'doctor'),
    ];

    expect($use->heardIn('his lower back hurts and the pain is sharp when he bends', $targets, [], $en))
        ->toBe(['reception:p1', 'doctor:p3'])
        ->and($use->heardIn('', $targets, ['doctor:p6'], $en))->toBe([]);
});

// The cards keep their channel: «Скажи целиком», «Говорю сам» and the slot judge read SpeechMatch, and this rule is
// not it. CATCHES a change of the talk's rule leaking into the cards' — the card's `free` reading of the gym day's key
// still does not pass «Weekdays works for me», exactly as before the наряд.
it('leaves the cards\' own rule of speech as it was', function () {
    $en = lessonPacks()->for('en');

    expect((new SpeechMatch)->said('Weekdays works for me', 'That works for me on', SpeechMode::Free, $en->speech()))->toBeFalse()
        ->and((new SpeechMatch)->said('That works for me on weekdays', 'That works for me on', SpeechMode::Free, $en->speech()))->toBeTrue();
});
