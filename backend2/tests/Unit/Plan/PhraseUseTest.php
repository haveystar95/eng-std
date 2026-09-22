<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;

/**
 * «СКАЗАЛ КОНСТРУКЦИЮ» (наряд FIX-3 §6): a target of the talk is a frame with a window, and it is said when the frame's
 * key words sounded and SOMETHING of the learner's own went into the window — theirs, the lesson's or the model's ear for
 * it. One case per rule (а)–(д), and the owner's own moves of the gym talks, day 1 and day 2 (GYM-DUMP-2), with what a
 * person counted there: day 1 — two or three, day 2 — at least «I'm working on ___».
 *
 * The targets are the constructions of the owner's plan «Тренировка в зале» — «Ресепшен зала» (day 1) and «С тренером»
 * (day 2) — as the lesson wrote them.
 */
function puTarget(string $ref, string $frame, ?string $example = null, string $scene = 'gym', ExchangeKind $kind = ExchangeKind::Answer): ConversationPhrase
{
    return new ConversationPhrase($scene, $ref, $frame, '', $example, null, $kind);
}

// (а)+(б) The key words are the frame's words outside its window that carry something — `unstressed_words` out; fewer
// than two such, and every word of the frame is its key. CATCHES the window counted as a key word, the unstressed words
// asked for, and a frame of one content word read by that one word alone.
it('keys a construction by its own words outside the window, and a frame of one such word by all of them', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');

    expect($use->keyTally("I'm working on general fitness", puTarget('p1', "I'm working on ___."), $en))->toBe(['found' => 2, 'total' => 2])
        ->and($use->keyTally('I have lower back pain', puTarget('p2', 'I have ___ of experience.'), $en))->toBe(['found' => 1, 'total' => 2])
        // «This is ___» has one word that carries something: then «this» AND «is» are its key.
        ->and($use->keyTally('Yes it is my first visit', puTarget('p3', 'This is ___.'), $en))->toBe(['found' => 1, 'total' => 2])
        // Contractions and numbers read the same on both sides.
        ->and($use->keyTally('I will rest for 45 seconds', puTarget('p6', "I'll rest for ___."), $en))->toBe(['found' => 2, 'total' => 2]);
});

// (в) The window is filled by the learner's own value — between the frame's parts, or, said elsewhere, whatever the frame's
// own words do not account for; an unstressed word alone fills nothing. CATCHES a window read as «the lesson's value»,
// a value taken with the words around it, and «I am working on it's» emptiness counted as a value.
it('reads the learner\'s own value out of the window, wherever the move put it', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');

    expect($use->valueOf('Hi I am working on general fitness', puTarget('p1', "I'm working on ___.", 'general fitness'), $en))->toBe('general fitness')
        ->and($use->valueOf("I don't have any experience", puTarget('p2', 'I have ___ of experience.', 'about a year'), $en))->toBe('any')
        ->and($use->valueOf('Yes it is my first visit', puTarget('p3', 'This is ___.', 'my first visit'), $en))->toBe('my first visit')
        ->and($use->valueOf('Weekdays works for me', puTarget('p4', 'That works for me on ___.', 'weekdays'), $en))->toBe('Weekdays')
        ->and($use->valueOf('I will rest for forty five seconds', puTarget('p6', "I'll rest for ___.", 'forty-five seconds'), $en))->toBe('forty five seconds')
        // A value ends where its sentence does (the rehearsal of the FIX-3 live run).
        ->and($use->valueOf("ok. he's been sick for two days. should I tell you about his sleep", puTarget('p2', "He's been sick ___.", 'for three days'), $en))->toBe('for two days')
        ->and($use->valueOf('I am working on the', puTarget('p1', "I'm working on ___.", 'general fitness'), $en))->toBeNull()
        // «my» waits for its noun: it fills nothing (the last check-in of the FIX-3 live run).
        ->and($use->valueOf('here is my', puTarget('p1', 'Here is ___.', 'my passport'), $en))->toBeNull()
        // The window left empty at the end of a move is empty: the sentence before it is not its value.
        ->and($use->valueOf("here is my passport. I'm flying to", puTarget('p2', "I'm flying to ___.", 'Madrid'), $en))->toBeNull()
        ->and($use->valueOf('Yes', puTarget('p5', 'Do you have a day pass?'), $en))->toBeNull();
});

// (г) The model's word is the second support, never the first: a target the rule did not find counts when the role named
// it, half of its key words were heard AND its window is filled. CATCHES the model trusted alone, and the model ignored.
it('takes the model\'s word only for a move that holds half of the key words and a value in the window', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $visit = puTarget('p3', 'This is ___.', 'my first visit');
    $weekdays = puTarget('p4', 'That works for me on ___.', 'weekdays');
    $monthly = puTarget('p2', 'What ___ do you have?', 'monthly memberships', kind: ExchangeKind::Ask);

    expect($use->heardIn('Yes it is my first visit', [$visit], [], $en))->toBe([])
        ->and($use->heardIn('Yes it is my first visit', [$visit], ['gym:p3'], $en))->toBe(['gym:p3'])
        ->and($use->heardIn('Weekdays works for me', [$weekdays], ['gym:p4'], $en))->toBe(['gym:p4'])
        // The role «heard» «What ___ do you have?» in a move that holds none of its key words: nothing.
        ->and($use->heardIn('No I want to buy monthly membership', [$monthly], ['gym:p2'], $en))->toBe([])
        // …and a name the move does not hold half of credits nothing either.
        ->and($use->heardIn('Hello I need daily training', [puTarget('p1', 'Do you have ___?', 'a day pass')], ['gym:p1'], $en))->toBe([]);
});

// (д) and THE OWNER'S TALKS: day 1 by the rule and the role's word — «This is ___» and «That works for me on ___» (a
// person counted two or three); day 2 — «I'm working on ___» by the rule alone, and «I have ___ of experience» said with
// «any» (a person: at least the first). «Yes my» is no value; «I have lower back pain» is not «I have some ___».
it('counts the owner\'s gym talks the way a person would', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $day1 = [
        puTarget('p1', 'Do you have ___?', 'a day pass', 'r', ExchangeKind::Ask),
        puTarget('p2', 'What ___ do you have?', 'monthly memberships', 'r', ExchangeKind::Ask),
        puTarget('p3', 'This is ___.', 'my first visit', 'r'),
        puTarget('p4', 'That works for me on ___.', 'weekdays', 'r'),
        puTarget('p5', 'Where are ___?', 'the changing rooms', 'r', ExchangeKind::Ask),
        puTarget('p6', "I'll return ___.", 'the locker key', 'r'),
        puTarget('p7', 'Can I pay ___?', 'by card', 'r', ExchangeKind::Ask),
    ];
    $day2 = [
        puTarget('p1', "I'm working on ___.", 'general fitness', 't'),
        puTarget('p2', 'I have ___ of experience.', 'about a year', 't'),
        puTarget('p3', 'I have some ___.', 'shoulder pain', 't'),
        puTarget('p4', 'How do I use ___?', 'this machine', 't', ExchangeKind::Ask),
        puTarget('p5', 'How heavy should ___ be?', 'the weight', 't', ExchangeKind::Ask),
        puTarget('p6', "I'll rest for ___.", 'forty-five seconds', 't'),
        puTarget('p7', 'Should I keep ___ down?', 'my shoulders', 't', ExchangeKind::Ask),
    ];
    // Each move as heard, with what the role named in its answer to it (the model's raw replies, GYM-DUMP-2).
    $walk = static function (array $targets, array $moves) use ($use, $en): array {
        $said = [];
        foreach ($moves as [$heard, $named]) {
            $unsaid = array_values(array_filter($targets, static fn (ConversationPhrase $t): bool => ! in_array($t->id(), $said, true)));
            $said = [...$said, ...$use->heardIn($heard, $unsaid, $named, $en)];
        }

        return $said;
    };

    expect($walk($day1, [
        ['Hello I need daily training', []],
        ['No I want to buy monthly membership', ['r:p2', 'r:p3']],
        ['Yes it is my first visit', ['r:p3']],
        ['Weekdays works for me', ['r:p4']],
    ]))->toBe(['r:p3', 'r:p4'])
        ->and($walk($day2, [
            ['Hi I am working on general fitness', ['t:p1']],
            ["I don't have any experience", []],
            ['Yes my', []],
            ['I have lower back pain', []],
        ]))->toBe(['t:p1', 't:p2']);
});

// A QUESTION IS ASKED WITH ITS OPENING WORD (rule г): the rehearsal of the FIX-3 live run heard «I told you. a cough.
// he's been sick for two days» and credited «Should I tell you ___?» — every key word there, and nothing asked. CATCHES a
// question credited to a statement that holds its other words, and a question refused for the want of a «?».
it('credits a question only to a move that opens it, with or without its question mark', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $tell = puTarget('p3', 'Should I tell you ___?', 'about his sleep', 't', ExchangeKind::Ask);
    $gate = puTarget('p7', 'Where is ___?', 'the gate', 'r', ExchangeKind::Ask);

    expect($use->heardIn("I told you. a cough. he's been sick for two days", [$tell], ['t:p3'], $en))->toBe([])
        ->and($use->heardIn('should I tell you about his cough', [$tell], [], $en))->toBe(['t:p3'])
        ->and($use->heardIn('perfect thank you. where is my gate', [$gate], [], $en))->toBe(['r:p7'])
        ->and($use->heardIn('my gate is it far', [$gate], ['r:p7'], $en))->toBe([]);
});

// A MOVE THAT BREAKS OFF (наряд FIX-3 §7: «обрывок ≠ „не понял"»): it stops on a word no sentence ends on, or right
// where the window of a construction opens. CATCHES «I'm flying to» (the live check-in run) and «Yes my» (the gym day)
// read as finished, and a finished line — «I told her», «I'm flying to Lisbon» — read as broken off.
it('knows a move that broke off from one that was finished', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');
    $targets = [puTarget('p2', "I'm flying to ___.", 'Madrid', 'r'), puTarget('p5', 'Is ___ allowed?', 'my dog', 'r', ExchangeKind::Ask)];

    expect($use->breaksOff("I'm flying to", $targets, $en))->toBeTrue()
        ->and($use->breaksOff('Yes my', $targets, $en))->toBeTrue()
        ->and($use->breaksOff('I have a', $targets, $en))->toBeTrue()
        ->and($use->breaksOff("sorry. I'm flying to Lisbon", $targets, $en))->toBeFalse()
        ->and($use->breaksOff('I told her', $targets, $en))->toBeFalse()
        ->and($use->breaksOff('', $targets, $en))->toBeFalse();
});

// The cards keep their channel: «Скажи целиком», «Говорю сам» and the slot judge read SpeechMatch, and this rule is
// not it. CATCHES a change of the talk's rule leaking into the cards'.
it('leaves the cards\' own rule of speech as it was', function () {
    $en = lessonPacks()->for('en');

    expect((new SpeechMatch)->said('Weekdays works for me', 'That works for me on', SpeechMode::Free, $en->speech()))->toBeFalse()
        ->and((new SpeechMatch)->said('That works for me on weekdays', 'That works for me on', SpeechMode::Free, $en->speech()))->toBeTrue();
});

// The echo guard's measure of a line against a move — the share of the line's key words the move holds, by their bases,
// in any order (наряд BACK-TAILS-2 §9) — is unchanged. CATCHES the construction rule leaking into the guard.
it('measures a line against a move by the share of its key words, persons swapped on demand', function () {
    $use = new PhraseUse;
    $en = lessonPacks()->for('en');

    expect($use->share('It started three days ago.', 'His lower back hurts, and three days ago it started.', $en))->toBe(1.0)
        ->and($use->share('Your son has a fever.', 'My son has a fever', $en, swapPersons: true))->toBe(1.0)
        ->and($use->bases('stopped', $en))->toContain('stop')
        ->and($use->bases('children', $en))->toContain('child');
});
