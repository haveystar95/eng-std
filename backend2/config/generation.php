<?php

declare(strict_types=1);

return [
    'plan' => [
        'day' => [
            // WHAT A REPAIR MOVE SOUNDS LIKE — the learner saying they did not catch it.
            //
            // Every day of a plan should carry at least one, and the day that made this a rule was
            // eight «I…» statements in a row with no way out of a misheard question
            // (docs/research/plan-v0.2.1-run.md, «Глазами»). The check is a WARNING, never a
            // refusal: a day missing one is a slightly worse day, not a broken one.
            //
            // A LIST AND NOT A RULE, and that is why it is here rather than in the validator. It
            // will be wrong — too narrow on a day that says «Sorry, what was that?», too broad if
            // somebody adds «sorry» on its own — and being wrong about a phrase list should not be
            // a code change. Matched as whole words inside the normalised line, so «repeat»
            // catches «Could you repeat the question?» and does not catch «repeatedly».
            //
            // KEYED BY TARGET LANGUAGE, because a repair move is a phrase and phrases do not
            // translate as a set. An EMPTY list means «nobody has written this language's list
            // yet» and switches the check off for it — a day in that language is never warned
            // about a missing repair move, which is the honest behaviour: the alternative is
            // warning about every German day because the list is English.
            'repair_markers' => [
                'en' => [
                    'repeat',
                    'say that again',
                    'slow down',
                    "didn't catch",
                    'breaking up',
                    'not sure I understood',
                ],
                // TODO: заполнить, когда появится первый живой немецкий план. Пустой список =
                // проверка на починку для немецкого выключена (не срабатывает и не падает).
                'de' => [],
            ],

            // СКОЛЬКО БУКВ СЛОВА — ЕГО ОСНОВА, по языку ПОДДЕРЖКИ (на котором написан перевод).
            //
            // Гейт «перевод реплики содержит перевод ключевой карточки» ищет слова ключа в
            // переводе фразы, а русский их склоняет: «долгое проживание» стоит в реплике как
            // «долгого проживания». Сравниваются первые N букв — самое грубое, что работает, и
            // намеренно не морфология: отказ дня должен объясняться человеку одной фразой.
            //
            // Число, а не список, но живёт здесь по той же причине: это суждение о языке, оно
            // окажется неверным первым же, и менять его деплоем домена — неправильно.
            //
            // ЯЗЫК, КОТОРОГО ЗДЕСЬ НЕТ, НЕ СУДИТСЯ ВОВСЕ — как и с починками: «немецкое правило
            // ещё не написано» не должно читаться как «у каждого немецкого дня сломан перевод».
            'translation_stems' => [
                'ru' => 5,
                'uk' => 5,
                // TODO: подобрать для de, когда появится первый живой немецкий план.
            ],
        ],
    ],
];
