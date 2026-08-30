<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\DifficultyScorer;

beforeEach(fn () => $this->scorer = new DifficultyScorer());

it('scores a bare word by its length alone', function () {
    expect($this->scorer->score('en', 'backend'))->toBe(2)
        ->and($this->scorer->score('en', 'code review'))->toBe(4);
});

it('charges a subordinate clause twice what a modal costs', function () {
    // «I can help» — 3 words × 2 = 6, + 1 modal = 7.
    // «I help because I know» — 5 × 2 = 10, + 2 clause = 12.
    expect($this->scorer->score('en', 'I can help'))->toBe(7)
        ->and($this->scorer->score('en', 'I help because I know'))->toBe(12);
});

it('finds the past in both a regular ending and an irregular form', function () {
    expect($this->scorer->score('en', 'I worked'))->toBe(5)      // 2×2 + 1
        ->and($this->scorer->score('en', 'I went'))->toBe(5);    // 2×2 + 1
});

it('reads the perfect continuous as past', function () {
    // «My back has been hurting» — 5 × 2 = 10, + 1 past.
    expect($this->scorer->score('en', 'My back has been hurting'))->toBe(11);
});

it('charges a question and a negation', function () {
    expect($this->scorer->score('en', 'Where does it hurt?'))->toBe(9)          // 4×2 + 1 question
        ->and($this->scorer->score('en', "I don't know"))->toBe(7);            // 3×2 + 1 negation
});

it('stacks every marker a real reply carries', function () {
    // «Could you repeat the last part?» — 6 × 2 = 12, + 1 modal, + 1 question = 14.
    expect($this->scorer->score('en', 'Could you repeat the last part?'))->toBe(14);
});

it('reads German markers: the ge- participle, a modal and a clause', function () {
    expect($this->scorer->score('de', 'Ich habe gearbeitet'))->toBe(7)              // 3×2 + 1 past
        ->and($this->scorer->score('de', 'Ich kann nicht kommen'))->toBe(10);       // 4×2 + modal + negation
});

it('has no opinion about the grammar of a language it was never taught', function () {
    // Romanian: length only, and no English marker is allowed to fire inside a Romanian word.
    // «Am venit astăzi cu motanul pentru vaccin.» is 7 words and nothing else.
    expect($this->scorer->score('ro', 'Am venit astăzi cu motanul pentru vaccin.'))->toBe(14);
});

it('orders the sandbox day the way a reader would', function () {
    $day = [
        'motanul',
        'A tușit de două ori astăzi.',
        'Am venit astăzi cu motanul pentru vaccin.',
        'vaccin',
    ];
    $scored = [];
    foreach ($day as $text) {
        $scored[$text] = $this->scorer->score('ro', $text);
    }
    asort($scored);

    expect(array_keys($scored))->toBe([
        'motanul',
        'vaccin',
        'A tușit de două ori astăzi.',
        'Am venit astăzi cu motanul pentru vaccin.',
    ]);
});

it('scores an empty string as zero rather than as one word', function () {
    expect($this->scorer->score('en', '   '))->toBe(0);
});
