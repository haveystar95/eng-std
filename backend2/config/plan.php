<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| THE LEARNING PLAN (docs/plan-v2.md)
|--------------------------------------------------------------------------
|
| Two model calls — the plan builder and the lesson generator — read from versioned prompt files
| (app/Modules/Plan/Infrastructure/Prompt), checked in code, dealt into days by the server.
|
*/
return [
    'model' => [
        // 'openai' | 'anthropic' | 'xai' | 'gemini' — one provider for both calls, or 'fake'
        // (deterministic, no network; the whole test suite runs on it, see phpunit.xml).
        'driver' => env('PLAN_MODEL_DRIVER', env('GENERATION_DRIVER', 'openai')),
        'provider' => env('PLAN_MODEL_PROVIDER', 'openai'),
        // The plan is written once and read for its whole life; the lesson is the day's material.
        // Both default to the same strong model the card core runs on (bakeoff-v11-ab, К2).
        'plan_model' => env('PLAN_BUILDER_MODEL', 'gpt-5.4'),
        'lesson_model' => env('PLAN_LESSON_MODEL', 'gpt-5.4'),
        // The repair of ONE card (P2R) — a few lines and the part of the lesson they need. A step cheaper was the
        // order of GEN-2b «if it repairs no worse», and it did not pass: `gpt-5.4-mini` answered a closing question by
        // deleting its question mark and broke the dialogue's fillers where the lesson's model did not (report GEN-2b
        // §4) — so the lesson's model stays until the architect says otherwise; the knob is here.
        'repair_model' => env('PLAN_REPAIR_MODEL', 'gpt-5.4'),
        // The seam judge — one yes or no per native sentence of the day, one call a day: a step cheaper.
        'judge_model' => env('PLAN_JUDGE_MODEL', 'gpt-5.4-mini'),
        // How long our client waits for the vendor's ANSWER, seconds (the connection itself — ten, VendorCall). Both calls
        // are asynchronous jobs the client polls; the repair and the seam judge run inside the lesson's job and take the
        // lesson's. 180 is three times the slowest lesson seen (51 s, наряд GEN-3): a client that gave up at 60 s paid
        // for a lesson the model finished and nobody read.
        'plan_timeout' => (int) env('PLAN_BUILDER_TIMEOUT', 180),
        'lesson_timeout' => (int) env('PLAN_LESSON_TIMEOUT', 180),
    ],

    // A build that started and never came back in this many seconds counts as dead: the client sees `failed` and may ask
    // for a retry — by hand, nothing retries it on its own. Longer than the lesson's job may run (every call it can make ×
    // the timeout, plus a minute — BuildLessonJob), so a retry never races a job that is still waiting for its answer.
    'build_stale_seconds' => (int) env('PLAN_BUILD_STALE_SECONDS', 1020),

    // What a lesson orders, per level: VOCABULARY_COUNT and DIALOGUE_COUNT. The number of frames is
    // not ordered — `lesson_day.v4.6` takes it from the dialogue (half to all of its answer/ask exchanges).
    'counts' => [
        'beginner' => ['vocabulary' => 8, 'dialogue' => 8],
        'intermediate' => ['vocabulary' => 8, 'dialogue' => 8],
    ],

    /*
     * THE PLAN CHECKS (docs/plan-v2.md §4): `observe` counts and keeps, `drop` erases the broken mark
     * or field, `gate` refuses the answer and buys one retry. Every check ships as `observe`; the
     * admin panel shows the counters per prompt version, and a mode is switched HERE, after real
     * plans, never in code. Checks marked «observe навсегда» in the canon ignore this table. The
     * lesson validator has no modes: it only counts (наряд GEN-2a); what it knows of a language is that
     * language's pack, `config/lesson/lang/<code>.php` (наряд GEN-2b).
     */
    'checks' => [
        'plan' => [
            'plan_shape' => env('PLAN_CHECK_PLAN_SHAPE', 'observe'),
            'priorities' => env('PLAN_CHECK_PRIORITIES', 'observe'),
            'topic_parts' => env('PLAN_CHECK_TOPIC_PARTS', 'observe'),
            'goals_count' => env('PLAN_CHECK_GOALS_COUNT', 'observe'),
        ],
    ],

    /*
     * THE RESCUE KIT — five phrases of the language pack, the same in every plan of that pair.
     * A static list, not a model call: the model asked a hundred times writes them a hundred ways.
     * Shown beside the plan; the day is not built from them.
     */
    'rescue_kit' => [
        'en' => [
            'ru' => [
                ['text_target' => 'Could you speak more slowly, please?', 'text_native' => 'Помедленнее, пожалуйста.', 'pronunciation_native' => 'куд ю спик мор слоули плиз'],
                ['text_target' => 'Could you write it down, please?', 'text_native' => 'Напишите, пожалуйста.', 'pronunciation_native' => 'куд ю райт ит даун плиз'],
                ['text_target' => 'Could you repeat that, please?', 'text_native' => 'Повторите ещё раз, пожалуйста.', 'pronunciation_native' => 'куд ю рипит зэт плиз'],
                ['text_target' => 'How much is it?', 'text_native' => 'Сколько это стоит?', 'pronunciation_native' => 'хау мач из ит'],
                ['text_target' => 'One moment, let me check.', 'text_native' => 'Секунду, я проверю.', 'pronunciation_native' => 'уан моумент лет ми чек'],
            ],
        ],
    ],

    // THE LANGUAGES A PLAN MAY BE BUILT IN — the server's list, not a client constant (owner's
    // decision, PLAN-UI-3): `GET /plans/languages` hands it to the entry screen and `POST /plans`
    // refuses anything else. Comma-separated codes.
    'languages' => array_values(array_filter(array_map('trim', explode(',', (string) env('PLAN_LANGUAGES', 'en,de'))))),

    // Where the day's audio files land (both speakers, phrases, words) — a private disk, served by the plan's own route.
    'audio_disk' => env('PLAN_AUDIO_DISK', env('SPEECH_DISK', 'local')),

    // Where the square copies of scene photos land (`plan-images/<scene>/<112|448>.jpg`) — fetched
    // once from the photo's CDN and served by `GET /plans/images/{scene}/{size}` (PLAN-UI-3).
    'image_disk' => env('PLAN_IMAGE_DISK', 'local'),

    /*
     * THE PACE OF A DAY — seconds per card, by kind (наряд SESSION-1a, разд. 2): what every «≈ N мин» of the
     * day window is counted from. Initial values from the order — tune them here after the phone, never in
     * code. A kind missing from the table costs nothing (`Domain/Service/DayPace`).
     */
    'pace' => [
        'word_intro' => 8,
        'word_repeat' => 12,
        'word_choose' => 10,
        'word_listen' => 10,
        'word_assemble' => 20,
        'word_in_line' => 10,
        'phrase_intro' => 12,
        'phrase_assemble' => 25,
        'phrase_choose_back' => 12,
        'phrase_slot' => 12,
        'phrase_slot_listen' => 12,
        'phrase_repeat' => 25,
        // «Скажи целиком» (наряд FIX-2, п. 5) is a SERIES: every value of the window said aloud and the learner's own
        // one after them, all on one card — so this is the price of ONE ROUND and the card's payload says how many
        // it has (`plan.phrases_budget` cuts rounds off it, and a flat per-card price would hide that).
        'phrase_other_slot' => 25,
        'phrase_combine' => 20,
        'dialogue_partner' => 15,
        'dialogue_answer' => 30,
        // The ask carries the exchange's check too since наряд BACK-TAILS-1 §1.5 — the two cards merged, and so do
        // their seconds: 30 said aloud + 15 tapped, exactly what `dialogue_ask` and `dialogue_partner` cost apart.
        'dialogue_ask' => 45,
        'dialogue_rescue' => 15,
        'listen_dialogue' => 110,
        'listen_question' => 12,
        'listen_review' => 30,
        'listen_predict' => 15,
        'listen_pace' => 25,
        'listen_number' => 15,
        'speak_answer' => 35,
        'speak_echo' => 25,
        'speak_retell' => 30,
        // «Вспомни свои реплики» (кадр 37-3, наряд CONV-1): the plan's own lines read through once,
        // scene by scene — a minute of reading and listening, not a trainer.
        'recall_scenes' => 60,
    ],

    /*
     * HOW LONG «ФРАЗЫ» MAY TAKE before the stage starts cutting itself (решение архитектора 20.09, доработка наряда
     * FIX-2). The day's own ceiling — 32 minutes — is unchanged; this is the stage's, by {@see \App\Modules\Plan\Domain\Service\DayPace}.
     *
     * Over it the stage is cut in ONE order: the third recognition, then the third value round of «Скажи целиком»,
     * then the own-word round — off the frames the dialogue says least first. The trainer itself is never removed,
     * and a stage that will not fit even then is dealt anyway: the excess is a signal in the build log, not a
     * refusal to build the day (`Domain/Assembly/PhrasesStage`).
     */
    'phrases_budget' => (int) env('PLAN_PHRASES_BUDGET', 690),

    /*
     * ПОТОЛОК ПЯТИ ЭТАПОВ КАРТОЧЕК, МИНУТ (решение владельца 21.09, наряд CONV-1).
     *
     * «День дольше 32 минут — стоп до ворот» сказано о РАЗДАЧЕ: если слова, фразы, диалог, слушание и
     * речь вместе стали дороже, значит правило числа узнаваний посчитано слишком щедро. Разговор в
     * этот потолок НЕ входит — у него свой бюджет (`plan.conversation.minutes`), заданный числом
     * ходов, а не раздачей; длительность дня на экране при этом складывается из обоих
     * ({@see \App\Modules\Plan\Domain\Service\DayBudget}).
     */
    'day_cards_budget' => (int) env('PLAN_DAY_CARDS_BUDGET', 32),

    /*
     * HOW MUCH OF A LINE ON THE SCREEN MAY GO MISSING (наряд FIX-2, п. 2) — the loosening handle of the `repeat`
     * mode ({@see \App\Modules\Shared\Domain\ValueObject\SpeechMode}): how many CONTENT words of the expected
     * text the learner may drop and still be counted as having said it.
     *
     * ZERO, and deliberately: the text is in front of them, the words a recogniser actually eats are already left
     * out of the comparison on both sides (the target pack's `unstressed_words`), and «He has a rush» for «He has a
     * rash» is what a share of 70 % accepted on the owner's phone. It is a config value and not a constant so that
     * a device that starts failing healthy sentences can be answered without a release — raise it, restart Horizon,
     * watch. The number also goes out to the phone with the day, so both sides count the same.
     */
    'speech' => [
        'repeat_misses' => (int) env('PLAN_SPEECH_REPEAT_MISSES', 0),
    ],

    /*
     * THE TALK WITH THE AGENT — the sixth stage of a day (наряд CONV-1, `docs/plan-v2.md`).
     *
     * `turns` is how many moves of the SCENE each kind of talk has: at nought the prompt is told
     * `TURNS_LEFT: 0` and the role says goodbye itself — nothing cuts a learner off mid-word. A
     * «Не понял» is not one of these moves (переспросы нейтральны, кадр 37-12); it is paid for out
     * of the money instead.
     *
     * `cost_cap_usd` is what one talk may spend on the model and the voice together. Reaching it
     * makes the NEXT move the role's last (`ended_reason: limit`), it does not end the talk where
     * the learner stands.
     *
     * `model` is a `mini` class on purpose: a move has six seconds to come back and a talk is a
     * dozen of them. `timeout` is the seconds of its ONE attempt — a retry would double a wait the
     * learner is sitting through.
     */
    'conversation' => [
        /*
         * РУБИЛЬНИК РАЗДАЧИ (хвост наряда CONV-1). Выключенный — день раздаётся БЕЗ шестого этапа:
         * `has_conversation = false`, пять этапов на экране, закрытие дня без 409, `POST …/conversation`
         * отвечает 422 — ровно тот же путь, которым живут дни, розданные до наряда.
         *
         * Это про РАЗДАЧУ, а не про чтение: день, уже розданный с разговором, остаётся с ним —
         * состав дня фиксируется при первом открытии (FIX-2 §7), и рубильник его не переписывает.
         * На бою выключен до сдачи клиентского наряда с разговором: сервер умеет, клиент ещё нет.
         */
        'enabled' => (bool) env('PLAN_CONVERSATION_ENABLED', true),
        'turns' => [
            'day' => (int) env('PLAN_CONVERSATION_TURNS_DAY', 4),
            'rehearsal' => (int) env('PLAN_CONVERSATION_TURNS_REHEARSAL', 10),
            'review' => (int) env('PLAN_CONVERSATION_TURNS_REVIEW', 4),
        ],
        'minutes' => [
            'day' => (int) env('PLAN_CONVERSATION_MINUTES_DAY', 3),
            'rehearsal' => (int) env('PLAN_CONVERSATION_MINUTES_REHEARSAL', 6),
            'review' => (int) env('PLAN_CONVERSATION_MINUTES_REVIEW', 6),
        ],
        'cost_cap_usd' => (float) env('PLAN_CONVERSATION_COST_CAP_USD', 0.08),
        'hint_delay_ms' => (int) env('PLAN_CONVERSATION_HINT_DELAY_MS', 5000),
        'model' => env('PLAN_CONVERSATION_MODEL', 'gpt-5.4-mini'),
        'timeout' => (int) env('PLAN_CONVERSATION_TIMEOUT', 20),
    ],

    /*
     * THE SLOT JUDGE — `slot_judge.v1` (наряд SESSION-1a, разд. 4): one synchronous call inside
     * `POST …/cards/{card}/judge` for the cards judged by meaning. `daily_cap` calls per learner per
     * local day, counted in `quota_store` (`redis` in the stack; `array` under test, phpunit.xml);
     * `timeout` seconds of the ONE attempt — past it the verdict is the code's, never a wait.
     */
    'slot_judge' => [
        'daily_cap' => (int) env('PLAN_SLOT_JUDGE_DAILY_CAP', 60),
        'quota_store' => env('PLAN_SLOT_JUDGE_QUOTA_STORE', 'redis'),
        'timeout' => (int) env('PLAN_SLOT_JUDGE_TIMEOUT', 8),
    ],
];
