<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\NotificationTexts;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE SERVER'S LINES ABOUT THE LEARNER, IN THE LEARNER'S GENDER (наряд FIX-3 §1: «родовые строки натива: ученик — по
 * профилю»): the summary's highlights, the plan's promise, the judge's «не сказал главного», the letters — «Сказала сама»
 * for a woman, «Сказал сам» for a man and for a profile that says nothing (the masculine default, DECISIONS). English has
 * nothing to agree.
 */

// CATCHES a line about the learner left in the masculine for a woman, and a profile that says nothing read as feminine.
it('says every line about the learner in the gender of their profile, the masculine when it says nothing', function () {
    $of = static fn (?VoiceGender $g, string $lang = 'ru'): array => [
        (new NativeStrings($lang, $g))->highlight('said_self', 5, 'phrase_of', 7),
        (new NativeStrings($lang, $g))->highlight('understood_all', 0, 'question_of'),
        (new NativeStrings($lang, $g))->planSummary(['Ресепшен зала'], new DateTimeImmutable('2026-10-02')),
        (new NativeStrings($lang, $g))->judgeReason('main', 'в какие дни'),
        (new NotificationTexts($lang, $g))->dailyReminder(2, 'С тренером')->body,
        (new NotificationTexts($lang, $g))->eventToday('Тренировка')->body,
    ];

    expect($of(VoiceGender::Female))->toBe([
        'Сказала сама 5 фраз из 7',
        'Поняла все вопросы',
        'Ресепшен зала. Ко 2 октября скажешь всё это сама',
        'Не сказала главного — в какие дни',
        '«С тренером» — начни с того места, где остановилась',
        'Скажи сама перед разговором — прогони его вслух',
    ])
        ->and($of(VoiceGender::Male))->toBe([
            'Сказал сам 5 фраз из 7',
            'Понял все вопросы',
            'Ресепшен зала. Ко 2 октября скажешь всё это сам',
            'Не сказал главного — в какие дни',
            '«С тренером» — начни с того места, где остановился',
            'Скажи сам перед разговором — прогони его вслух',
        ])
        ->and($of(null))->toBe($of(VoiceGender::Male))
        ->and($of(VoiceGender::Female, 'uk')[0])->toBe('Сказала сама 5 фраз із 7')
        ->and($of(VoiceGender::Female, 'en'))->toBe($of(VoiceGender::Male, 'en'));
});
