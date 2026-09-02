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

            // СТОП-СПИСОК БАЗОВОГО (канон §7) — числа, дни недели, семья, цвета, местоимения,
            // be/have/go и подобное. День-сцена держит около двадцати пяти единиц и это всё, что
            // ученик встретит до события: слот, потраченный на «Monday», — это реплика про жар у
            // ребёнка, которой у него не будет.
            //
            // Фатально от уровня «Понимаю простое» и выше, у `zero` — счётчик: начинающий
            // действительно встречает «one» и «Monday» впервые, и та же карточка, которая на B1
            // потраченный слот, на A0 — это урок.
            //
            // СПИСОК, А НЕ ПРАВИЛО, поэтому здесь: он окажется неверным, и «неверный список слов»
            // не должен быть правкой домена. Языка нет в карте — проверка для него выключена.
            // Дефолт живёт в `Generation\Domain\Service\BasicVocabulary`; здесь — то, чем его
            // переопределяют.
            'basic_stop_list' => [],
        ],

        /**
         * СПАСАТЕЛЬНЫЙ НАБОР — пять фраз языкового пакета (канон §5).
         *
         * Не генерация: это одни и те же пять фраз в каждом плане этого языка, и модель, которую
         * спросили о них сто раз, напишет их ста способами. Сервер вшивает их в день 1 и раздаёт в
         * разогреве каждой сессии; P2 видит их только запретным списком.
         *
         * Ключ — ПАРА: фраза одна для всех, кто учит английский, а русский под ней зависит от того,
         * кто учит. Пары нет в пакете — плана без набора, а не отказ собирать план.
         */
        'rescue_kit' => [
            'en' => [
                'ru' => [
                    [
                        'text' => 'Could you speak more slowly, please?',
                        'translation' => 'Помедленнее, пожалуйста.',
                        'transliteration' => 'куд ю спик мор слоули плиз',
                        'example' => 'Sorry, could you speak more slowly, please? My English is not very good.',
                        'example_translation' => 'Извините, помедленнее, пожалуйста. Мой английский не очень.',
                        'image_api_prompt' => 'a person at a counter raising a hand slightly while listening carefully to a member of staff',
                    ],
                    [
                        'text' => 'Could you write it down, please?',
                        'translation' => 'Напишите, пожалуйста.',
                        'transliteration' => 'куд ю райт ит даун плиз',
                        'example' => 'Could you write it down, please? I want to get the name right.',
                        'example_translation' => 'Напишите, пожалуйста. Хочу записать название правильно.',
                        'image_api_prompt' => 'a hand writing a note on a small pad on a reception desk',
                    ],
                    [
                        'text' => 'Could you repeat that, please?',
                        'translation' => 'Повторите ещё раз, пожалуйста.',
                        'transliteration' => 'куд ю рипит зэт плиз',
                        'example' => 'Sorry, could you repeat that, please? I did not catch the last part.',
                        'example_translation' => 'Извините, повторите ещё раз, пожалуйста. Я не расслышал последнюю часть.',
                        'image_api_prompt' => 'two people talking across a desk, one leaning in to listen',
                    ],
                    [
                        'text' => 'How much is it?',
                        'translation' => 'Сколько это стоит?',
                        'transliteration' => 'хау мач из ит',
                        'example' => 'How much is it, with everything included?',
                        'example_translation' => 'Сколько это стоит, со всем вместе?',
                        'image_api_prompt' => 'a customer pointing at a price list on a counter',
                    ],
                    [
                        'text' => 'One moment, let me check.',
                        'translation' => 'Секунду, я проверю.',
                        'transliteration' => 'уан моумент лет ми чек',
                        'example' => 'One moment, let me check — I have it on my phone.',
                        'example_translation' => 'Секунду, я проверю — это у меня в телефоне.',
                        'image_api_prompt' => 'a person looking at their phone while someone waits across a counter',
                    ],
                ],
            ],
        ],
    ],
];
