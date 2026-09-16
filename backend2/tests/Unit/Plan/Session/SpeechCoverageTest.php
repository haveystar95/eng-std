<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\SpeechCoverage;

/**
 * THE COVERAGE OF A LINE SAID ALOUD (наряд SESSION-1a, разд. 0; D-26): all of a line of at most two words, 70 % of a
 * longer one counted as a multiset, the target pack's articles forgiven — and nothing forgiven for a pack without them.
 */

it('asks all of a line of at most two counted words and most of a longer one', function () {
    $coverage = new SpeechCoverage;
    $en = lessonPacks()->for('en');

    expect($coverage->minFor('a headache', $en))->toBe(1.0)
        ->and($coverage->minFor('the X-ray', $en))->toBe(1.0)
        ->and($coverage->minFor('I have a fever', $en))->toBe(0.7)
        ->and($coverage->countedWords('I have a fever.', $en))->toBe(3)
        // Short: one word missing is a miss.
        ->and($coverage->covers('sick', 'sick note', 1.0, $en))->toBeFalse()
        ->and($coverage->covers('Sick note!', 'sick note', 1.0, $en))->toBeTrue()
        // Long: 3 of 3 counted, 2 of 3 is under 0.7.
        ->and($coverage->covers('i have fever', 'I have a fever.', 0.7, $en))->toBeTrue()
        ->and($coverage->covers('have fever', 'I have a fever.', 0.7, $en))->toBeFalse()
        // 7 of 10 is exactly the bar.
        ->and($coverage->covers('one two three four five six seven', 'one two three four five six seven eight nine ten', 0.7, $en))->toBeTrue()
        ->and($coverage->covers('whatever', '', 0.7, $en))->toBeFalse();
});

// Catches a set instead of a multiset: «no» said once does not cover a line that says it three times.
it('counts the expected words as a multiset, each heard word used once', function () {
    $coverage = new SpeechCoverage;
    $en = lessonPacks()->for('en');

    expect($coverage->covers('no', 'no no no', 0.7, $en))->toBeFalse()
        ->and($coverage->covers('very good day', 'very very good day', 0.7, $en))->toBeTrue()
        ->and($coverage->covers('very good', 'very very very good', 0.7, $en))->toBeFalse();
});

// Catches a hard-coded a/an/the: Russian names no articles, so its «a» is a word like any other.
it('forgives the articles the target pack names, and none for a pack without articles', function () {
    $coverage = new SpeechCoverage;

    expect($coverage->covers('headache', 'a headache', 1.0, lessonPacks()->for('en')))->toBeTrue()
        ->and($coverage->minFor('a headache', lessonPacks()->for('ru')))->toBe(1.0)
        ->and($coverage->countedWords('a headache', lessonPacks()->for('ru')))->toBe(2)
        ->and($coverage->covers('headache', 'a headache', 1.0, lessonPacks()->for('ru')))->toBeFalse()
        ->and($coverage->minFor('the back pain', lessonPacks()->for('en')))->toBe(1.0)
        ->and($coverage->minFor('the back pain', lessonPacks()->for('ru')))->toBe(0.7);
});

it('finds a value said as one run of words, articles aside', function () {
    $coverage = new SpeechCoverage;
    $en = lessonPacks()->for('en');

    expect($coverage->containsSequence('Yes, I have a bad headache since morning', 'a bad headache', $en))->toBeTrue()
        ->and($coverage->containsSequence('I have bad the headache', 'bad headache', $en))->toBeTrue()
        ->and($coverage->containsSequence('I have a headache that is bad', 'bad headache', $en))->toBeFalse()
        ->and($coverage->containsSequence('I have a headache', 'the', $en))->toBeFalse()
        ->and($coverage->containsSequence('headache', 'bad headache', $en))->toBeFalse()
        ->and($coverage->containsSequence('I have a headache', 'a headache', lessonPacks()->for('ru')))->toBeTrue()
        ->and($coverage->containsSequence('I have headache', 'a headache', lessonPacks()->for('ru')))->toBeFalse();
});

// The code's verdict when the judge is silent: what was heard beyond the frame, as heard, in order.
it('takes the words heard beyond the frame as the slot, in the order heard', function () {
    $coverage = new SpeechCoverage;

    expect(FrameParts::part('I have ___.'))->toBe('I have')
        ->and($coverage->slotWords('I have a terrible headache.', 'I have ___.'))->toBe('a terrible headache')
        ->and($coverage->slotWords('Well, I have, I have a cough', 'I have ___.'))->toBe('Well I have a cough')
        ->and($coverage->slotWords('I have', 'I have ___.'))->toBe('')
        ->and($coverage->slotWords("It's my back, I'd like a doctor", "I'd like a ___, please."))->toBe("It's my back doctor")
        // Heard is folded WHOLE, as the coverage folds it: «It's» is `it is` alone and `it has` in front of «been»,
        // so a word folded on its own comes back as the learner's slot when the frame is said the way it is written.
        ->and($coverage->slotWords("It's been a long day", "It's been a ___ day."))->toBe('long')
        ->and($coverage->slotWords("He's been resting since Monday", "He's been resting ___ ."))->toBe('since Monday')
        ->and($coverage->slotWords('He has been resting since Monday', "He's been resting ___ ."))->toBe('since Monday');
});
