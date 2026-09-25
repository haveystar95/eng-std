<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\ReplyNative;

/**
 * THE GUARD OF THE ROLE'S TRANSLATION (наряд FIX-4c §6): «reply_native не может совпадать с reply_target (без регистра и
 * знаков) и должен быть на родном языке пары (по письменности пакета)».
 */

// Canon (§6). CATCHES the FIX-4b rehearsal's turn 14 — «How long has he had these symptoms?» under itself — passed as a
// translation, an English line with Russian marks passed, an empty one passed, and a Russian line refused for naming a
// drug or a scan in Latin letters.
it('finds the translation missing when it is empty, the same words, or not in the learner\'s letters', function () {
    $ru = lessonPacks()->for('ru');
    $line = 'How long has he had these symptoms?';

    expect(ReplyNative::missing($line, '', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, '  — ', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, $line, $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, 'how long has he had these symptoms', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, 'How long has he had the symptoms?', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, 'Как давно у него эти симптомы?', $ru))->toBeFalse()
        ->and(ReplyNative::missing('Did you give him Ibuprofen?', 'Вы давали ему Ibuprofen?', $ru))->toBeFalse()
        ->and(ReplyNative::missing('He needs an MRI.', 'Ему нужна МРТ, то есть MRI.', $ru))->toBeFalse();
});

// Canon (§6): a language whose pack does not write its letters is judged by the two plain checks only. CATCHES a guard
// that refuses every line of such a pair, or none.
it('judges a language without its letters written by emptiness and sameness only', function () {
    $en = lessonPacks()->for('en');

    expect(ReplyNative::missing('Bună ziua.', 'Good afternoon.', $en))->toBeFalse()
        ->and(ReplyNative::missing('Bună ziua.', 'Bună ziua!', $en))->toBeTrue()
        ->and(ReplyNative::missing('Bună ziua.', '', $en))->toBeTrue();
});
