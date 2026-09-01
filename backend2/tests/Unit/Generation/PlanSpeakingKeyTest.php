<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PlanSpeakingKey;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;

function dayCard(string $text, string $kind = PlanDayItem::KIND_WORD): PlanDayItem
{
    return new PlanDayItem(
        text: $text,
        type: 'word',
        kind: $kind,
        isLine: false,
        translation: 'перевод',
        transliteration: null,
        description: 'a thing',
        example: "Something about {$text}.",
        exampleTranslation: 'Что-то.',
    );
}

function dayLine(string $text, string $frame = '', string $filler = ''): PlanDayItem
{
    return new PlanDayItem(
        text: $text,
        type: 'phrase',
        kind: PlanDayItem::KIND_LINE,
        isLine: true,
        translation: 'перевод реплики',
        transliteration: null,
        description: 'somebody says it',
        example: $text,
        exampleTranslation: 'Перевод.',
        frame: $frame,
        filler: $filler,
        speaker: PlanDayItem::SPEAKER_LEARNER,
    );
}

it('takes the frame’s filler — the piece the line exists to drill', function () {
    $line = dayLine(
        "Yes, I'm looking for a place to rent for long-term living.",
        frame: "Yes, I'm looking for ___ for long-term living.",
        filler: 'a place to rent',
    );

    expect(PlanSpeakingKey::of($line, [$line, dayCard('a place to rent')]))->toBe('a place to rent');
});

it('falls back to a day card standing inside a formula', function () {
    // No hole, but the day still teaches «next month» and the formula is built around it.
    $line = dayLine('I need to move in next month.');

    expect(PlanSpeakingKey::of($line, [$line, dayCard('next month'), dayCard('a studio')]))
        ->toBe('next month');
});

it('takes the LONGEST card inside the line, because that is the one the day teaches', function () {
    $line = dayLine('Is there anything close to the city center?');
    $items = [$line, dayCard('the city'), dayCard('close to the city center', PlanDayItem::KIND_CHUNK)];

    expect(PlanSpeakingKey::of($line, $items))->toBe('close to the city center');
});

it('matches a card on word boundaries and not inside a longer word', function () {
    $line = dayLine('The current price is fine.');

    expect(PlanSpeakingKey::of($line, [$line, dayCard('rent')]))->toBeNull();
});

it('answers NULL for a whole move with no piece in it — and null means «say it all»', function () {
    $line = dayLine('Sorry, could you repeat that?');

    expect(PlanSpeakingKey::of($line, [$line, dayCard('a studio'), dayCard('next month')]))->toBeNull();
});

it('has nothing to say about a word or a connector', function () {
    $word = dayCard('a studio');

    expect(PlanSpeakingKey::of($word, [$word, dayLine('A studio works for me.')]))->toBeNull();
});
