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

    // HOW LONG A WRONG ANSWER MAY BE, relative to the target. The shape rule (DistractorFamily)
    // says a word stands beside a word; inside one shape, length still gives the answer away —
    // `key` offered `accommodation`, `neighbourhood`, `responsibility` is answered by picking the
    // short one without reading it. It got worse the day the option pool became the whole catalogue.
    //
    // Two measures because a phrase is not a long word: single lexical items are compared by
    // CHARACTERS, a spoken line by WORDS. Configuration and not constants — this is a product
    // judgement about how hard a card should be, and the first time one of them is wrong it should
    // move without a deploy. The rule itself lives in Shared\Domain\Service\DistractorLength and
    // is read by everyone who builds or predicts a choice card.
    'distractor_length' => [
        'char_tolerance' => 0.5,   // word / chunk / «no kind» — ±50 % of the target's characters
        'word_tolerance' => 0.4,   // line — ±40 % of the target's word count
    ],

    // A PLAN'S CHOICE CARD: what the level WANTS, and how far it may shrink before it is dropped.
    //
    // The level's number is a preference (`PlanKnobs::$mcOptions` — three at zero/basic, four from
    // conversational up). This is the floor under it. They used to be the same number, and on the
    // owner's live day 1 that cost eleven cards of fourteen their recognition step: four options
    // wanted, the length band narrow by design, and a day of fourteen cards simply does not hold
    // four same-shape same-length terms for most of them. Stage A collapsed to «met it → said it».
    //
    // Three is a card — one right answer and two wrong ones, which is exactly what a `zero` learner
    // has always been dealt. Below three it is a coin toss, and the card still falls out whole.
    //
    // Configuration and not a constant, like the band above: a product judgement about how hard a
    // card should be. The rule itself lives in Learning\Domain\Service\PlanChoiceFloor and is read
    // by BOTH the checklist (what a card is owed) and the assembler (what can be built), because a
    // step that is owed and cannot be dealt is a stage that never closes.
    'plan' => [
        'mc_min_options' => 3,

        // БЮДЖЕТ ДНЯ ПЛАНА — решение владельца 05.09 (наряд DAY-FIX-2, Ч.2). Числа продуктовые,
        // поэтому конфиг: первый раз, когда одно из них окажется неверным, оно должно сдвинуться
        // без выката. Читает {@see \App\Modules\Learning\Application\Service\PlanSittingPlanner}.
        'budget' => [
            // День = ОДИН присест не длиннее этого. Второй присест бывает только под прогон сцены.
            // Дневной материал (разогрев, слова, знакомство, диалог) в потолок укладывается по
            // построению лестницы; шов и вчерашние промахи режутся с хвоста, пока не влезет.
            'sitting_max_cards' => 40,
            // Секция «Слова и связки» за посадку: свои слова дня первыми, слова из дней позади —
            // пока влезают. Остальное ждёт следующего дня.
            'words_section_cards' => 12,
            // Спасателей в разогреве — не больше стольких карточек.
            'rescue_warmup_cards' => 5,
            // Секунд на карточку — то, из чего считаются минуты дня на экранах (Ч.3): карточек ×
            // секунд, до целой минуты вверх. Замер живого дня 1 владельца: 60 ответов за 962 с.
            'card_seconds' => 16,
        ],

        // ПРОГОН СЦЕНЫ — ступень C: реплики на экране нет, есть подсказка и микрофон
        // (наряд SCENE-RUN, Ч.2). Все четыре числа — продуктовые суждения, поэтому конфиг, а не
        // константы в коде экрана: первый раз, когда одно из них окажется неверным, оно должно
        // сдвинуться без выката.
        // ЧЕРЕЗ ENV, а не литералами: три из четырёх чисел приходится двигать на стенде — сторож,
        // рассчитанный на живого человека, обрывает ход быстрее, чем автоматизация успевает по нему
        // кликнуть, и QA-прогон ступени C иначе не снять. Дефолты — продуктовые.
        'scene_run' => [
            // «СРАЗУ» — сколько секунд от начала прослушивания до ключа реплики считается ответом
            // без раздумья. Канон §4: «готовность единицы = C + скорость: успех, где ответ начат за
            // ≤ 3 секунды». Медленный успех остаётся успехом и в готовность не идёт.
            'fast_seconds' => (int) env('SCENE_RUN_FAST_SECONDS', 3),

            // СТОРОЖ: столько экран слушает, прежде чем сделать ход за человека. Тот же порог, что
            // у говорения фраз, и по той же причине — дольше пятнадцати секунд человек не
            // вспоминает, он мучается.
            'listen_seconds' => (int) env('SCENE_RUN_LISTEN_SECONDS', 15),

            // …и через столько секунд молчания появляется «Пропустить». Раньше сторожа, потому что
            // выход должен быть виден до того, как он понадобится.
            'skip_after_seconds' => (int) env('SCENE_RUN_SKIP_AFTER_SECONDS', 5),

            // ЦЕНА ОДНОГО ХОДА В МИНУТАХ ДНЯ. Присест считается карточками по 16 с
            // ({@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler}), а ход
            // прогона дороже: человек слушает реплику собеседника, вспоминает свою и говорит её.
            'turn_seconds' => 20,

            // ДОЛЯ «СРАЗУ», при которой сцена считается ГОТОВОЙ (наряд SCENE-RUN, Ч.3.2). Сцена, в
            // которой всё сказано самим, но медленно, — это «говоришь сам» и ещё не «готов»:
            // на стойке отвечают за три секунды, а не за двенадцать.
            'ready_fast_share' => 0.7,
        ],
    ],

    // The ADMISSION MATRIX — which rung of the acquisition ladder opens which trainer — is NOT
    // here. It is data, in `learning_mode_settings` beside the toggle above, with the same
    // global-plus-per-user-override mechanism: what a mode asks of the learner is a product
    // judgement that should move with one admin action, not with a deploy. The shipped values live
    // in `ModeAdmission::shipped()`, which seeds the table and is the fallback if it is emptied.
];
