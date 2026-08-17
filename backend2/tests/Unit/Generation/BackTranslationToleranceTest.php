<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\BackTranslationTolerance;

beforeEach(fn () => $this->tolerance = new BackTranslationTolerance());

/**
 * The two cases that define the boundary. Both are ONE token apart, which is why a plain
 * "at most one token differs" rule cannot express the difference and this class exists.
 */
it('suppresses an inflection of the same word', function () {
    expect($this->tolerance->isNearMiss('company policy', ['company policies']))->toBeTrue();
});

it('does NOT suppress a different word in the same slot', function () {
    expect($this->tolerance->isNearMiss('rest room', ['break room']))->toBeFalse();
});

it('suppresses an inserted function word', function () {
    // «meet with the team» for «meet the team» — the model padded its own paraphrase.
    expect($this->tolerance->isNearMiss('meet with the team', ['meet the team']))->toBeTrue();
});

it('suppresses a deleted function word', function () {
    expect($this->tolerance->isNearMiss('settle in', ['to settle in']))->toBeTrue();
});

it('suppresses an inserted infinitive marker', function () {
    expect($this->tolerance->isNearMiss('to introduce yourself', ['introduce yourself']))->toBeTrue();
});

it('does NOT suppress an inserted CONTENT word — that adds meaning, it does not restate it', function () {
    expect($this->tolerance->isNearMiss('open a bank account', ['open an account']))->toBeFalse();
});

it('does NOT suppress a deleted content word', function () {
    expect($this->tolerance->isNearMiss('the office', ['the office layout']))->toBeFalse();
});

it('suppresses a function word inserted at the very end', function () {
    // The insertion is the last token, so the walk never mismatches — the tail case.
    expect($this->tolerance->isNearMiss('settle in', ['settle']))->toBeTrue();
});

it('keeps a near-synonym that shares only a short prefix', function () {
    // «workplace» / «workstation» share "work", but that is 4 of 9 characters — a different word,
    // and a genuinely different answer to the same Russian prompt.
    expect($this->tolerance->isNearMiss('workplace', ['workstation']))->toBeFalse();
});

it('keeps a rewrite that differs by two tokens', function () {
    expect($this->tolerance->isNearMiss('to settle in', ['get settled in']))->toBeFalse();
});

it('keeps a completely different phrase', function () {
    expect($this->tolerance->isNearMiss('introductory briefing', ['orientation session']))->toBeFalse();
});

it('tolerates against ANY accepted form, not just the first', function () {
    expect($this->tolerance->isNearMiss('greet the teams', ['meet the team', 'greet the team']))->toBeTrue();
});

it('does not treat two short different words as the same stem', function () {
    // «is»/«in» are both under the stem floor; a prefix ratio on 2 characters is meaningless.
    expect($this->tolerance->isNearMiss('it is ready', ['it in ready']))->toBeFalse();
});

it('is unaffected by case, punctuation and a leading article (the shared normaliser runs first)', function () {
    expect($this->tolerance->isNearMiss('The Company Policy!', ['company policies']))->toBeTrue();
});

it('returns false for an empty back-translation', function () {
    expect($this->tolerance->isNearMiss('   ', ['break room']))->toBeFalse();
});

it('suppresses a longer phrase that differs only by one inflected token', function () {
    expect($this->tolerance->isNearMiss('get to know the offices', ['get to know the office']))->toBeTrue();
});

it('keeps a phrase where the differing token is a different lexeme', function () {
    expect($this->tolerance->isNearMiss('learn the office', ['get to know the office']))->toBeFalse();
});
