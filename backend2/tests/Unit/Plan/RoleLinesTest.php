<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Assembly\Options;
use App\Modules\Plan\Domain\Service\RoleLines;

/**
 * THE ROLE SAYS ITS OWN LINES (наряд CONV-2, пп. 1 и 4б) — the server's two guards over every reply of the role, checked on
 * the owner's own talks of 21.09 (`docs/research/conv-2/den/`): the learner lines are the key lines those talks were
 * given, the replies are what the role said, word for word.
 */

/** The learner's lines of «Ресепшен зала» (the gym day 1 of plan 01M32DX8…), as the talk was told them. */
function rlGym(): array
{
    return [
        'Do you have a day pass?', 'What monthly memberships do you have?', 'Yes, this is my first visit.',
        'That works for me on weekdays.', 'Where are the changing rooms?', "Okay, I'll return the locker key.", 'Can I pay by card?',
    ];
}

/** The learner's lines of the rehearsal «Звонок агенту» + «Просмотр жилья» (plan 01M2TSRM…, day 3). */
function rlFlat(): array
{
    return [
        'Is this flat two rooms?', 'What is the rent?', 'Is parking included?', 'Are dogs allowed?', 'I can follow that rule.',
        'How much is the deposit?', 'Can we meet Friday evening?', 'Can I see the kitchen?', 'That sounds in good condition.',
        'How does the heating work?', 'I can live with light traffic.', 'What are the rules for my dog?',
        'Can I get a parking space?', 'Could you explain the contract term?', "I'd like to take the flat.",
    ];
}

/**
 * Canon (п. 1): «ответ роли, совпадающий по Options::APART ≥ 0,5 с любой репликой ученика из плана, отбрасывается». The
 * gym day: the receptionist asked the member's questions — every one of them is caught, including the one hidden in the
 * first half of a longer question. Catches a guard that reads whole replies only (43 % for the third one) and a guard
 * that lets the role open with the learner's first line.
 */
it('catches the lines the gym receptionist said in the member\'s place', function () {
    expect(RoleLines::learnerLineIn('Do you have a day pass?', rlGym(), ['Hello I need daily training']))->toBe('Do you have a day pass?')
        ->and(RoleLines::learnerLineIn(
            'Great, then I can show you the monthly options. What monthly memberships do you have in mind, and do you need weekdays only?',
            rlGym(), ['Hello I need daily training', 'No I want to buy monthly membership', 'Yes it is my first visit'],
        ))->toBe('What monthly memberships do you have?')
        ->and(RoleLines::learnerLineIn('Great, that works. Where are the changing rooms?', rlGym(), ['Weekdays works for me']))
        ->toBe('Where are the changing rooms?')
        // …and the receptionist's own lines stand: the greeting, and the question the partner line of x3 asks.
        ->and(RoleLines::learnerLineIn('Hello! Welcome to the gym. How can I help you today?', rlGym()))->toBeNull()
        ->and(RoleLines::learnerLineIn('We have a few monthly memberships. This is your first visit, right?', rlGym(), ['No I want to buy monthly membership']))->toBeNull();
});

/**
 * Canon (п. 1): the rehearsal «Просмотр жилья» — the agent asked the tenant's questions from the first line to the last.
 * Eight of the nine are caught; the ninth («What is the rent you want to ask about?», 44 %) stands below the measure, and
 * the next reply, «What is the rent?», is caught. The agent's own question after a refusal is left alone.
 */
it('catches the tenant\'s questions the agent asked in the rehearsal', function () {
    $caught = [
        ['Hello, this is the agent. Is this flat two rooms?', '', 'Is this flat two rooms?'],
        ['Yes, it is a two-room flat. What is the rent?', 'It is two room flat', 'What is the rent?'],
        ['Yes, the rent is $800 per month. Is parking included?', 'The rent is $800 per month', 'Is parking included?'],
        ['Right, parking is not included. Are dogs allowed?', 'No parking is not included', 'Are dogs allowed?'],
        ['Yes, dogs are allowed, but there is an extra deposit. How much is the deposit?', 'Yes dogs allowed but it will cost double deposit', 'How much is the deposit?'],
        ['Yes, the deposit is one month’s rent. Can we meet Friday evening?', 'Deposit is one month rent', 'Can we meet Friday evening?'],
        ['Great, Friday works. Can I see the kitchen?', 'Yes', 'Can I see the kitchen?'],
        ['Yes, there is a kitchen, and it is a studio. Can I see the kitchen?', "There are Kitchen but it's studio", 'Can I see the kitchen?'],
    ];
    foreach ($caught as [$reply, $heard, $line]) {
        expect(RoleLines::learnerLineIn($reply, rlFlat(), $heard === '' ? [] : [$heard]))->toBe($line, $reply);
    }

    expect(Options::share('What is the rent you want to ask about?', 'What is the rent?'))->toBeLessThan(Options::APART)
        ->and(RoleLines::learnerLineIn('Yes, it is a two-room flat. What is the rent you want to ask about?', rlFlat(), ['Hello yes this flight showrooms']))->toBeNull()
        ->and(RoleLines::learnerLineIn('Okay, Friday evening does not work. Can we meet on another day?', rlFlat(), ['No Friday evening I am busy']))->toBeNull();
});

/**
 * Canon (п. 1): what the guard must NOT take for the learner's part — the role answering them. A question answered with
 * its own words («Yes, parking is included.» — 75 % of «Is parking included?»), what the learner has said in the talk
 * said back to them — the last move or an earlier one — and a line too short to be anybody's. Catches a guard that sends
 * every echo answer back to the model: in a scene where the learner asks, that is every turn asked twice.
 */
it('leaves the role\'s answers alone: an answer in the question\'s words, an echo of the learner, a short line', function () {
    expect(Options::share('Yes, parking is included.', 'Is parking included?'))->toBeGreaterThanOrEqual(Options::APART)
        ->and(RoleLines::learnerLineIn('Yes, parking is included.', rlFlat(), ['Is parking included?']))->toBeNull()
        ->and(RoleLines::learnerLineIn('Yes, dogs are allowed, but you pay an extra deposit.', rlFlat(), ['Are dogs allowed?']))->toBeNull()
        // The doctor says back what the parent has just said — that is the role listening, not taking the parent's part.
        ->and(RoleLines::learnerLineIn('Your son has a fever. How long has he had it?', ['My son has a fever.'], ['My son has a fever.']))->toBeNull()
        // …or said two moves ago: an echo of an earlier move is an echo too.
        ->and(RoleLines::learnerLineIn('Your son has a fever. Does he sleep well?', ['My son has a fever.'], ['My son has a fever.', 'Three days.']))->toBeNull()
        // A statement is read whole: the first half of the receptionist's own sentence is not the member's line (the
        // replay of report §1, call 15 — 50 % for the clause, 24 % for the sentence).
        ->and(RoleLines::learnerLineIn('Great, weekdays work well. Since this is your first visit, I can also explain the rules if you need them.', rlGym(), ['Weekdays works for me']))->toBeNull()
        // The same sentence said BEFORE the parent said it is the parent's line in the doctor's mouth.
        ->and(RoleLines::learnerLineIn('Your son has a fever. How long has he had it?', ['My son has a fever.'], ['Hello.']))->toBe('My son has a fever.')
        ->and(RoleLines::learnerLineIn('Yes, please.', ['Yes, please.']))->toBeNull()
        ->and(RoleLines::learnerLineIn('I see.', ['I see.']))->toBeNull();
});

/**
 * Canon (п. 1): «одна попытка» — and when the answer asked for again says the learner's line too, the sentence that says it
 * goes, in the reply and in its translation alike, and the role's own sentences are said. The owner's rehearsal on the
 * same inputs (report §1): «Yes, it is a two-room flat. What is the rent?» twice → «Yes, it is a two-room flat.». Catches a
 * cut that leaves the translation saying what the reply no longer says, and a cut that leaves nothing.
 */
it('cuts the learner\'s line out of an answer that keeps saying it, sentence for sentence, when the rest stands', function () {
    expect(RoleLines::withoutLearnerLines(
        'Yes, it is a two-room flat. What is the rent?', 'Да, это двухкомнатная квартира. Какая арендная плата?', rlFlat(), ['It is two room flat'],
    ))->toBe(['target' => 'Yes, it is a two-room flat.', 'native' => 'Да, это двухкомнатная квартира.'])
        // Nothing of the role's own left — nothing to cut to.
        ->and(RoleLines::withoutLearnerLines('Do you have a day pass?', 'У вас есть дневной пропуск?', rlGym()))->toBeNull()
        // A translation that does not split the way the reply does is not cut into a lie.
        ->and(RoleLines::withoutLearnerLines('Sure. Do you have a day pass?', 'Конечно, у вас есть дневной пропуск?', rlGym()))->toBeNull()
        // An answer with no learner line is not «cut» at all.
        ->and(RoleLines::withoutLearnerLines('Sure. It is fifteen dollars.', 'Конечно. Пятнадцать долларов.', rlGym()))->toBeNull();
});

/**
 * Canon (п. 4б): «роль повторяет ПРОЩЕ: тот же смысл, другие слова, короче; страховка — ответ, совпадающий с предыдущей
 * репликой роли ≥ 0,7». Both live runs of CLIENT-CONV-1a got the rescued line back word for word. Catches a rescue that
 * repeats the line and a guard that refuses a real rephrasing.
 */
it('takes a rescue for the same line when it says the same words, and a rephrasing for a rephrasing', function () {
    expect(RoleLines::repeats('How long has he had it?', 'How long has he had it?'))->toBeTrue()
        ->and(RoleLines::repeats('I see. How long has he had the fever, and how high is it?', 'I see. How long has he had the fever, and how high is it?'))->toBeTrue()
        ->and(RoleLines::repeats('How many days has he had the fever?', 'I see. How long has he had the fever, and how high is it?'))->toBeFalse()
        ->and(RoleLines::repeats('How many days?', 'How long has he had it?'))->toBeFalse()
        // Nothing to rescue — nothing it can repeat.
        ->and(RoleLines::repeats('Hello.', null))->toBeFalse()
        ->and(RoleLines::SAME_WORDS)->toBe(0.7)
        ->and(RoleLines::LEARNER_LINE)->toBe(Options::APART);
});
