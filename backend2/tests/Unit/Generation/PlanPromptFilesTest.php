<?php

declare(strict_types=1);

use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;

/**
 * ФАЙЛ ПРОМПТА И ЕГО ВЕРСИЯ — ОДНО И ТО ЖЕ (наряд DAY-FIX-3, живой стенд 07.09).
 *
 * `PlanPromptLibrary` держит версию (`DAY_VERSION = plan_day.v0.8`) и имя файла порознь, и три
 * живых плана ушли в модель с ТЕКСТОМ v0.7 под схемой v0.8: константа файла отстала на одну
 * правку, реестр трат честно писал «v0.8», и ноль тематических слов в трёх планах подряд
 * списывался на модель. Замок: текст, который уезжает в модель, обязан нести метку той версии,
 * которую библиотека о нём говорит.
 */
it('renders every plan prompt from the file of the version it names', function () {
    $library = new PlanPromptLibrary();
    $blanks = array_fill_keys([
        'goal', 'level', 'target_lang', 'support_lang', 'target_lang_notes', 'support_lang_notes',
        'scene', 'known', 'rescue_kit', 'balance', 'days', 'event_date', 'minutes_per_day',
        'listening', 'cards', 'violations', 'pair', 'reason', 'role', 'you', 'day_words',
    ], '');

    foreach ([
        'outline' => [$library->outline($blanks), $library->outlineVersion()],
        'day' => [$library->day($blanks), $library->dayVersion()],
        'repair' => [$library->repair($blanks), $library->repairVersion()],
        'listen' => [$library->listen($blanks), $library->listenVersion()],
        'pairJudge' => [$library->pairJudge($blanks), $library->pairJudgeVersion()],
        'pairRewrite' => [$library->pairRewrite($blanks), $library->pairRewriteVersion()],
    ] as $name => [$rendered, $version]) {
        $file = __DIR__ . '/../../../app/Modules/Generation/Infrastructure/Prompt/' . $version . '.md';
        expect(file_exists($file))->toBeTrue("{$name}: no file for version {$version}");
        // The body the model gets is the file's body, verbatim up to the placeholders: the first
        // fifty characters after the header rule are the same string in both.
        $body = trim(implode("\n---\n", array_slice(explode("\n---\n", (string) file_get_contents($file)), 1)));
        expect(substr($rendered->text, 0, 40))->toBe(substr(strtr($body, ['{{' => '{{']), 0, 40), "{$name}: rendered text is not the body of {$version}.md");
    }

    // …and the day prompt in particular says what v0.8 is for.
    expect($library->day($blanks)->text)->toContain('TOPICAL CARDS');
});
