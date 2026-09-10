<?php

declare(strict_types=1);

return [
    // Exercise modes switched on, IN ROTATION ORDER — the free-practice round-robin indexes into
    // this list, so reordering it re-deals every card (the mobile client mirrors both the set and
    // the order, pinned by tests/Fixtures/practice-mode-contract.json).
    //
    // Recognition + assembly + typing, plus listening (hear it, type it, graded like typing), cloze
    // (fill the blank in the term's own example) and scramble (assemble the example sentence from
    // word chips). Which of them a given term can actually be drilled in is a separate question,
    // answered in one place by TermPlayability. The ExerciseSelector rotates/degrades only within
    // this set, so it never hands out a mode that is not built.
    //
    // This is the ONE runtime source of the mode set; it becomes a column of the learning policy
    // later, and nothing else should grow a second list.
    // `intro` is deliberately absent: a new trainer ships switched OFF globally and is turned on
    // from the admin panel (CLAUDE.md release rule). The panel lists it as soon as the enum knows
    // it. Until it is switched on, a brand-new pair simply starts one rung higher, at
    // recognition — exactly what happened before the ladder existed.
    'enabled_modes' => ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble'],

    // HOW LONG A WRONG ANSWER MAY BE, relative to the target: `key` offered `accommodation`,
    // `neighbourhood`, `responsibility` is answered by picking the short one without reading it.
    // Configuration and not a constant — a product judgement about how hard a card should be, and
    // the first time it is wrong it should move without a deploy. The rule itself lives in
    // Shared\Domain\Service\DistractorLength and is read by everyone who builds a choice card.
    'distractor_length' => [
        'char_tolerance' => 0.5,   // ±50 % of the target's characters
    ],

    // ЗАЧЁТ РЕЧИ — ПОРОГИ (наряд SPEECH-2, Ч.3.3). Продуктовые суждения о том, сколько реплики
    // человек обязан сказать, поэтому конфиг: первый раз, когда одно из них окажется неверным,
    // оно должно сдвинуться без выката приложения. ЭТИ ЖЕ числа едут телефону в контракте
    // сессии — экран и сервер судят одной функцией по одним порогам
    // ({@see \App\Modules\Learning\Domain\Service\SpokenLine}).
    'speech' => [
        // Фраза НА ЭКРАНЕ («Повтори вслух», чтение примера): текст перед глазами, и бар высокий.
        'read_aloud_coverage' => (float) env('SPEECH_READ_ALOUD_COVERAGE', 0.9),

        // Текста НЕТ («Скажи сам»): ключ обязателен, и с ним — столько ОСТАЛЬНЫХ слов реплики.
        // «Сказал проще» законно про фразу, а не про одно слово.
        'recall_rest_coverage' => (float) env('SPEECH_RECALL_REST_COVERAGE', 0.6),

        // Реплика БЕЗ КЛЮЧА судится целиком — тем же порогом, что и всегда.
        'whole_line_coverage' => (float) env('SPEECH_WHOLE_LINE_COVERAGE', 0.7),

        // Ниже этой доли «почти» превращается в «не то»: список «не хватило», в котором лежит
        // вся реплика, ничего человеку не объясняет.
        'almost_floor' => (float) env('SPEECH_ALMOST_FLOOR', 0.5),

        // Сколько безударных слов-связок прощается сверх порога при чтении с экрана. Артикли
        // не считаются вовсе и в это число не входят.
        'filler_allowance' => (int) env('SPEECH_FILLER_ALLOWANCE', 1),
    ],

    // The ADMISSION MATRIX — which rung of the acquisition ladder opens which trainer — is NOT
    // here. It is data, in `learning_mode_settings` beside the toggle above, with the same
    // global-plus-per-user-override mechanism: what a mode asks of the learner is a product
    // judgement that should move with one admin action, not with a deploy. The shipped values live
    // in `ModeAdmission::shipped()`, which seeds the table and is the fallback if it is emptied.
];
