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

        // ЗАСЧИТЫВАТЬ ЛИ УПРОЩЁННЫЕ ФОРМЫ ОТВЕТА НА ГОВОРЕНИИ (наряд GEN-1, канон Y4).
        //
        // P2 v0.7 пишет к каждой реплике `speaking_keys[]` — 1–2 более простых формы того же
        // ответа. Сервер хранит их и отдаёт клиенту полем `speaking_keys`, и ГРЕЙДЕР засчитывает
        // любую из них. ВКЛЮЧЁН с наряда DAY-FIX-3 (Ч.1.6): клиент судит говорение по тому же
        // списку (`SessionCard.spokenTargets`), и инвариант «проверка на телефоне никогда не
        // строже серверной» держится тем, что обе стороны читают ОДНО поле карточки. Тумблер
        // остался на случай отката клиента; выключенный — сервер судит по одному `speaking_key`,
        // то есть СТРОЖЕ телефона, и это единственное направление, в которое ручку двигать нельзя
        // без правки клиента. Замок — `PlanGen1CanonTest` («грейдер по умолчанию не строже
        // клиента»).
        'speaking_keys_graded' => (bool) env('PLAN_SPEAKING_KEYS_GRADED', true),

        // СКОЛЬКО МИНУТ ДЕНЬ МОЖЕТ ВИСЕТЬ В `generating`, ПРЕЖДЕ ЧЕМ ВОРКЕР СЧИТАЕТСЯ МЁРТВЫМ
        // (вердикт владельца по GEN-1). Джоба дня живёт до 420 с (два вызова модели по 180 с плюс
        // запись), так что десять минут — это «точно не идёт». Просроченный день перезахватывается
        // при следующем опросе или чтении плана: первый раз — снова в очередь, второй — `failed`
        // с причиной (`PlanDay::reclaimStale()`).
        'generation_stale_minutes' => (int) env('PLAN_GENERATION_STALE_MINUTES', 10),

        // БЮДЖЕТ ДНЯ ПЛАНА — решение владельца 05.09 (наряд DAY-FIX-2, Ч.2). Числа продуктовые,
        // поэтому конфиг: первый раз, когда одно из них окажется неверным, оно должно сдвинуться
        // без выката. Читает {@see \App\Modules\Learning\Application\Service\PlanSittingPlanner}.
        'budget' => [
            // ДВА ПРИСЕСТА, ДВА ПОТОЛКА (наряд DAY-FIX-3, Ч.4; владелец принял день ≈ 70 карточек,
            // ~18 минут). «Материал» — разогрев, слова и связки, знакомство с репликами и их
            // упражнения; «Разговор» — диалог сцены, реплики шва, прогон. Что не влезает, режется с
            // хвоста: у материала — шов, промахи, потом тематические слова; у разговора — шов,
            // потом прогон самой старой сцены целиком.
            'material_max_cards' => 45,
            'conversation_max_cards' => 25,
            // Из скольких вариантов слово выбирает перевод в день знакомства (Ч.3.1). Ручка уровня
            // (`mc_options`) правит всем остальным выбором.
            'word_choice_options' => 4,
            // Секция «Слова и связки» за посадку, КАРТОЧКАМИ: свои слова дня первыми, слова из
            // дней позади — пока влезают. Остальное ждёт следующего дня. С наряда DAY-FIX-3 у
            // слова два касания в день знакомства и до десяти тематических слов на день, так что
            // прежние 12 держали бы шов слов закрытым навсегда; секцию теперь держит потолок
            // материала, а эта ручка — верхняя граница на случай, если он поднимется.
            'words_section_cards' => 40,
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
