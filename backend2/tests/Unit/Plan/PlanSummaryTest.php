<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\ValueObject\Image;

/**
 * THE PLAN IN ONE SENTENCE (PLAN-UI-3, owner's decision): the first three scenes of the route,
 * lowercased and joined, the first letter raised, then the promise — dated when the plan has a date.
 * No model, no prompt, never stored.
 */
it('three scenes and a date make exactly the owner’s sentence', function () {
    $summary = (new NativeStrings('ru'))->planSummary(
        ['Регистрация на рейс', 'Заселение в отель', 'Ресторан'],
        new DateTimeImmutable('2026-09-17'),
    );

    expect($summary)->toBe('Регистрация на рейс, заселение в отель, ресторан. К 17 сентября скажешь всё это сам');
});

it('says «ко» only before 2 — catches «К 2 сентября» and an over-eager «Ко 12» / «Ко 22»', function (string $date, string $expected) {
    expect((new NativeStrings('ru'))->planSummary(['Аптека'], new DateTimeImmutable($date)))->toBe($expected);
})->with([
    '2' => ['2026-09-02', 'Аптека. Ко 2 сентября скажешь всё это сам'],
    '12' => ['2026-09-12', 'Аптека. К 12 сентября скажешь всё это сам'],
    '22' => ['2026-09-22', 'Аптека. К 22 сентября скажешь всё это сам'],
    '17' => ['2026-09-17', 'Аптека. К 17 сентября скажешь всё это сам'],
]);

it('a plan without a date promises without one', function () {
    expect((new NativeStrings('ru'))->planSummary(['Регистрация на рейс', 'Заселение в отель', 'Ресторан'], null))
        ->toBe('Регистрация на рейс, заселение в отель, ресторан. Скажешь всё это сам');
});

it('fewer than three scenes name what there is', function () {
    expect((new NativeStrings('ru'))->planSummary(['Аптека'], new DateTimeImmutable('2026-03-01')))
        ->toBe('Аптека. К 1 марта скажешь всё это сам');
});

it('names only the first three scenes of the route', function () {
    expect((new NativeStrings('ru'))->planSummary(['Запись к врачу', 'Приём у врача', 'Аптека', 'Анализы'], null))
        ->toBe('Запись к врачу, приём у врача, аптека. Скажешь всё это сам');
});

it('no scene titles, no summary', function () {
    expect((new NativeStrings('ru'))->planSummary([], null))->toBeNull()
        ->and((new NativeStrings('ru'))->planSummary(['  '], null))->toBeNull();
});

it('writes the promise in the learner’s own language and falls back to English', function () {
    $date = new DateTimeImmutable('2026-09-17');

    expect((new NativeStrings('uk'))->planSummary(['Реєстрація на рейс'], $date))->toBe('Реєстрація на рейс. До 17 вересня скажеш усе це сам')
        ->and((new NativeStrings('en'))->planSummary(['Hotel check-in', 'Restaurant'], $date))->toBe('Hotel check-in, restaurant. By September 17 you will say all of this yourself')
        ->and((new NativeStrings('pl'))->planSummary(['Hotel check-in'], null))->toBe('Hotel check-in. You will say all of this yourself');
});

it('keeps a photo’s tone only when it is a colour, and versions the photo by its address', function () {
    expect((new Image('https://x/a.jpg', null, null, '#978e82'))->tone)->toBe('#978E82')
        ->and((new Image('https://x/a.jpg', null, null, 'grey'))->tone)->toBeNull()
        ->and((new Image('https://x/a.jpg', null, null))->tone)->toBeNull()
        ->and((new Image('https://x/a.jpg', null, null))->version())->toBe(substr(sha1('https://x/a.jpg'), 0, 12))
        ->and((new Image('https://x/a.jpg', 'A', null, '#000000'))->version())->toBe((new Image('https://x/a.jpg', null, null))->version());
});
