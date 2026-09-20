<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;
use App\Modules\Shared\Domain\ValueObject\SpeechPack;

/**
 * THE ONE RULE OF SPOKEN GRADING (наряд FIX-2, п. 2; хвост SESSION-1a) — two modes, one implementation, and a language
 * described by its pack rather than by the code.
 *
 * `repeat` (the text is on screen) asks for every content word, in its order; `free` (the learner's own sentence) asks
 * for a share of the key. Every test below names the rule it holds and the defect it catches.
 */

/** English as the plan serves it to the phone: the pack's own lists, nothing invented here. */
function fix2En(): SpeechPack
{
    return lessonPacks()->for('en')->speech();
}

// Canon: «нормализация — регистр, знаки, сокращения, числа словом/цифрой, сокращённые формы (I'd = I would)». Catches a
// comparison done on the raw strings: the two readings below are the SAME sentence said by two recognisers.
it('reads a contraction, an abbreviation and a number written either way as one sentence', function () {
    $speech = new SpeechMatch;

    expect($speech->repeated("I would like the three pm appointment", "I'd like the 3 p.m. appointment", fix2En()))->toBeTrue()
        ->and($speech->repeated("I'd like the 3 p.m. appointment", 'I would like the three pm appointment', fix2En()))->toBeTrue()
        ->and($speech->words('3 p.m.', fix2En()))->toBe(['3', 'pm'])
        ->and($speech->words('three PM', fix2En()))->toBe(['3', 'pm']);
});

// Canon: «все смысловые слова ожидаемого текста на месте и по порядку… служебные слова не учитываются». Catches the
// share that let «He has a rush» pass for «He has a rash» on the owner's phone (проход 20.09, п. 2), and a rule that
// would fail a learner for the article the recogniser ate.
it('fails one content word said wrong and forgives an article a recogniser ate', function () {
    $speech = new SpeechMatch;

    expect($speech->repeated('He has a rash', 'He has a rash.', fix2En()))->toBeTrue()
        ->and($speech->repeated('He has a rush', 'He has a rash.', fix2En()))->toBeFalse()
        ->and($speech->repeated("I'd like the 3 p.m. appointment", "I'd like the 3 p.m. appointment", fix2En()))->toBeTrue()
        ->and($speech->repeated('I would like 3 pm appointment', "I'd like the 3 p.m. appointment", fix2En()))->toBeTrue()
        ->and($speech->repeated("I'd like the 3 p.m. visit", "I'd like the 3 p.m. appointment", fix2En()))->toBeFalse();
});

// Canon: «на месте и ПО ПОРЯДКУ… лишние слова в услышанном не мешают». Catches an order-free multiset let in by the
// back door — a sentence read backwards is not that sentence — and a rule that punishes what was said around the line.
it('asks the content words in their order and ignores whatever was said around them', function () {
    $speech = new SpeechMatch;

    expect($speech->repeated('Um, it hurts in his lower back, I think', 'It hurts in his lower back.', fix2En()))->toBeTrue()
        ->and($speech->repeated('his lower back hurts', 'It hurts in his lower back.', fix2En()))->toBeFalse()
        ->and($speech->repeated('It hurts in his back lower', 'It hurts in his lower back.', fix2En()))->toBeFalse();
});

// Canon: «ручка ослабления в config: допустимых пропусков смысловых слов в режиме „повтор" — 0». Catches a handle that
// is wired to nothing, and a default that is not zero.
it('drops nothing by default and exactly as many content words as the handle allows', function () {
    $speech = new SpeechMatch;

    expect($speech->repeated('It hurts in his back', 'It hurts in his lower back.', fix2En()))->toBeFalse()
        ->and($speech->repeated('It hurts in his back', 'It hurts in his lower back.', fix2En(), misses: 1))->toBeTrue()
        ->and($speech->repeated('It hurts', 'It hurts in his lower back.', fix2En(), misses: 1))->toBeFalse();
});

// Catches a hard-coded a/an/the and a hard-coded list of function words: a language whose pack names none forgives
// none, and a line of nothing but function words is asked for whole — there is no content to anchor on.
it('forgives only what the pack names, and asks a line of function words for whole', function () {
    $speech = new SpeechMatch;
    $none = SpeechPack::none();

    expect($speech->repeated('He has rash', 'He has a rash.', $none))->toBeFalse()
        ->and($speech->repeated('He has a rash', 'He has a rash.', $none))->toBeTrue()
        // «In the ___.» is nothing but function words: with nothing to anchor on, every word is asked for.
        ->and($speech->repeated('in the', 'In the ___.', fix2En()))->toBeTrue()
        ->and($speech->repeated('the', 'In the ___.', fix2En()))->toBeFalse();
});

// Canon (the `free` mode, unchanged): all of a short key, most of a longer one, as a MULTISET and order-free. Catches a
// short key graded leniently and a set instead of a multiset.
it('asks all of a short key and most of a long one, counting the words as a multiset', function () {
    $speech = new SpeechMatch;
    $en = fix2En();

    expect($speech->minFor('a headache', $en))->toBe(1.0)
        ->and($speech->minFor('I have a fever', $en))->toBe(0.7)
        ->and($speech->countedWords('I have a fever.', $en))->toBe(3)
        ->and($speech->said('sick', 'sick note', SpeechMode::Free, $en))->toBeFalse()
        ->and($speech->said('Sick note!', 'sick note', SpeechMode::Free, $en))->toBeTrue()
        ->and($speech->said('i have fever', 'I have a fever.', SpeechMode::Free, $en))->toBeTrue()
        ->and($speech->said('have fever', 'I have a fever.', SpeechMode::Free, $en))->toBeFalse()
        ->and($speech->said('one two three four five six seven', 'one two three four five six seven eight nine ten', SpeechMode::Free, $en))->toBeTrue()
        ->and($speech->said('whatever', '', SpeechMode::Free, $en))->toBeFalse()
        ->and($speech->covers('no', 'no no no', 0.7, $en))->toBeFalse()
        ->and($speech->covers('very good day', 'very very good day', 0.7, $en))->toBeTrue();
});

// Catches a hard-coded a/an/the in the `free` mode too: Russian names no articles, so its «a» is a word like any other.
it('forgives the articles the target pack names, and none for a pack without articles', function () {
    $speech = new SpeechMatch;
    $ru = lessonPacks()->for('ru')->speech();

    expect($speech->covers('headache', 'a headache', 1.0, fix2En()))->toBeTrue()
        ->and($speech->minFor('a headache', $ru))->toBe(1.0)
        ->and($speech->countedWords('a headache', $ru))->toBe(2)
        ->and($speech->covers('headache', 'a headache', 1.0, $ru))->toBeFalse()
        ->and($speech->minFor('the back pain', fix2En()))->toBe(1.0)
        ->and($speech->minFor('the back pain', $ru))->toBe(0.7);
});

it('finds a value said as one run of words, articles aside', function () {
    $speech = new SpeechMatch;
    $en = fix2En();
    $ru = lessonPacks()->for('ru')->speech();

    expect($speech->containsSequence('Yes, I have a bad headache since morning', 'a bad headache', $en))->toBeTrue()
        ->and($speech->containsSequence('I have bad the headache', 'bad headache', $en))->toBeTrue()
        ->and($speech->containsSequence('I have a headache that is bad', 'bad headache', $en))->toBeFalse()
        ->and($speech->containsSequence('I have a headache', 'the', $en))->toBeFalse()
        ->and($speech->containsSequence('headache', 'bad headache', $en))->toBeFalse()
        ->and($speech->containsSequence('I have a headache', 'a headache', $ru))->toBeTrue()
        ->and($speech->containsSequence('I have headache', 'a headache', $ru))->toBeFalse();
});

// The code's verdict when the judge is silent: what was heard beyond the key, as heard, in order.
it('takes the words heard beyond the key as the slot, in the order heard', function () {
    $speech = new SpeechMatch;
    $en = fix2En();

    expect(FrameParts::part('I have ___.'))->toBe('I have')
        ->and($speech->slotWords('I have a terrible headache.', FrameParts::part('I have ___.'), $en))->toBe('a terrible headache')
        ->and($speech->slotWords('Well, I have, I have a cough', FrameParts::part('I have ___.'), $en))->toBe('Well I have a cough')
        ->and($speech->slotWords('I have', FrameParts::part('I have ___.'), $en))->toBe('')
        ->and($speech->slotWords("It's my back, I'd like a doctor", FrameParts::part("I'd like a ___, please."), $en))->toBe("It's my back doctor")
        // Heard is folded WHOLE, as the ratio folds it: «It's» is `it is` alone and `it has` in front of «been», so a
        // word folded on its own comes back as the learner's slot when the key is said the way it is written.
        ->and($speech->slotWords("It's been a long day", FrameParts::part("It's been a ___ day."), $en))->toBe('long')
        ->and($speech->slotWords("He's been resting since Monday", FrameParts::part("He's been resting ___ ."), $en))->toBe('since Monday')
        ->and($speech->slotWords('He has been resting since Monday', FrameParts::part("He's been resting ___ ."), $en))->toBe('since Monday');
});

// The recogniser's own two habits, forgiven wherever words are counted — they used to live in Learning alone, and Plan
// graded without them. Catches the two rules drifting apart again.
it('forgives a boundary the recogniser guessed and a trailing sibilant it did not hear, in both modes', function () {
    $speech = new SpeechMatch;
    $none = SpeechPack::none();

    expect($speech->repeated('I see withoututilities', 'I see without utilities', $none))->toBeTrue()
        ->and($speech->covers('I see withoututilities', 'I see without utilities', 0.7, $none))->toBeTrue()
        ->and($speech->repeated('salary expectation', 'salary expectations', $none))->toBeTrue()
        ->and($speech->covers('salary expectation', 'salary expectations', 1.0, $none))->toBeTrue();
});
