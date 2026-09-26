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
    // for a retry — by hand, nothing retries a DEAD build on its own (the one rebuild the server makes is inside the build,
    // for a lesson that failed the gate — наряд LANG-1b §1). Longer than the lesson's job may run (every call it can make ×
    // the timeout, plus a minute — BuildLessonJob, 1 680 s), so a retry never races a job that is still waiting for its answer.
    'build_stale_seconds' => (int) env('PLAN_BUILD_STALE_SECONDS', 1740),

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

    // THE RESCUE KIT is its target's — the pack's `rescue` key, translated into every learner's language
    // (`config/lesson/lang/<target>.php`, наряд LANG-1b §2): no list of its own here any more.

    // THE LANGUAGES A PLAN MAY BE BUILT IN — the server's list, not a client constant (owner's
    // decision, PLAN-UI-3): `GET /plans/languages` hands it to the entry screen and `POST /plans`
    // refuses anything else (`language_pair_invalid`, наряд LANG-1 §7).
    //
    // The list itself lives in code, in ONE place — `LanguageRoles::planTargets()` (DECISIONS п. 145) —
    // and with no `PLAN_LANGUAGES` this is all of it. The env var is a NARROWING override and nothing
    // more (a list behind a flag, п. 82): comma-separated codes, of which only the plan targets count,
    // in the order of `planTargets()` whatever order they are written in (`PlanServiceProvider`). It
    // cannot add a language — a code outside the targets is dropped — and a list that keeps none of them
    // offers none. Unset or empty = every target. A change needs `app` and `horizon` restarted.
    'languages' => (static function (): array {
        $narrowed = array_values(array_filter(array_map('trim', explode(',', (string) env('PLAN_LANGUAGES', '')))));

        return $narrowed !== [] ? $narrowed : \App\Modules\Shared\Domain\Service\LanguageRoles::planTargets();
    })(),

    // Where the day's audio files land (both speakers, phrases, words) — a private disk, served by the plan's own route.
    'audio_disk' => env('PLAN_AUDIO_DISK', env('SPEECH_DISK', 'local')),

    // Where the square copies of scene photos land (`plan-images/<scene>/<112|448>.jpg`) — fetched
    // once from the photo's CDN and served by `GET /plans/images/{scene}/{size}` (PLAN-UI-3).
    'image_disk' => env('PLAN_IMAGE_DISK', 'local'),

    /*
     * THE PACE OF A DAY — seconds per card, by kind (наряд SESSION-1a, разд. 2): what every «≈ N мин» of the
     * day window is counted from. MEASURED ON THE PHONE (наряд FIX-3 §2): the median of the seconds a card of the
     * kind took on every answered day of the live server — the gap from the day's previous answer, gaps over two
     * minutes left out, the first answer of a day carrying none — times 1.3 and rounded to 5 s; a kind nobody
     * answered yet takes its old price times the measured kinds' ratio. The first list (the order's guesses) read
     * the owner's days 2–2.5 times too long. A plan keeps the list it was made with (`plans.pace`) until
     * `php artisan plan:repace` gives it this one. A kind missing from the table costs nothing (`Domain/Service/DayPace`).
     */
    'pace' => [
        'word_intro' => 5,
        'word_repeat' => 10,
        'word_choose' => 5,
        'word_listen' => 5,
        'word_assemble' => 10,
        'word_in_line' => 10,
        'phrase_intro' => 15,
        'phrase_assemble' => 20,
        'phrase_choose_back' => 10,
        'phrase_slot' => 10,
        'phrase_slot_listen' => 5,
        'phrase_repeat' => 20,
        // «Скажи целиком» (наряд FIX-2, п. 5) is a SERIES: every value of the window said aloud and the learner's own
        // one after them, all on one card — so this is the price of ONE ROUND and the card's payload says how many
        // it has (a flat per-card price would make a frame of two values cost what a frame of three does; the ladder
        // никогда не режет круги — наряд FIX-3 §3).
        'phrase_other_slot' => 25,
        'phrase_combine' => 25,
        'dialogue_partner' => 20,
        'dialogue_answer' => 10,
        // The ask carries the exchange's check too since наряд BACK-TAILS-1 §1.5 — measured as the one card it is.
        'dialogue_ask' => 25,
        'dialogue_rescue' => 10,
        'listen_dialogue' => 70,
        'listen_question' => 10,
        'listen_review' => 10,
        'listen_predict' => 15,
        'listen_pace' => 15,
        'listen_number' => 10,
        'speak_answer' => 25,
        'speak_echo' => 70,
        'speak_retell' => 15,
        // «Вспомни свои реплики» (кадр 37-3, наряд CONV-1): the plan's own lines read through once,
        // scene by scene — a minute of reading and listening A SCENE, not a trainer: the sheet is priced per scene it
        // shows (наряд BACK-TAILS-2 §4, like «Скажи целиком» per round).
        'recall_scenes' => 45,
    ],

    /*
     * HOW LONG «ФРАЗЫ» MAY TAKE before the stage starts cutting itself (решение архитектора 20.09, доработка наряда
     * FIX-2; 690 → 900 — приёмка окна 1 FIX-3, 22.09). The day's own ceiling — 32 minutes — is unchanged; this is the
     * stage's, by {@see \App\Modules\Plan\Domain\Service\DayPace}.
     *
     * NINE HUNDRED, because the prices are honest now (наряд FIX-3 §2) and the rounds are never cut (§3): a lesson of
     * `lesson_day.v4.7` costs 30–170 s more than the old ceiling, and a signal that fires on EVERY live day says
     * nothing. The ceiling is for an anomaly, not for the ordinary day.
     *
     * Over it the stage is cut in ONE order, a rung at a time until it fits (наряд FIX-3 §3): the third recognitions
     * are not added, then the second recognitions go — off the frames the dialogue says least first. The ROUNDS of
     * «Скажи целиком» are never cut: the value rounds the level deals and the learner's own word — never fewer than two
     * value rounds where the frame has two values or more. The floor is one recognition, all the rounds and the own word. A stage
     * that will not fit even then is dealt anyway: the excess is a warning in the day's build log
     * (`plan.phrases_over_ceiling`), not a refusal to build the day (`Domain/Assembly/PhrasesStage`).
     */
    'phrases_budget' => (int) env('PLAN_PHRASES_BUDGET', 900),

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
     * The learner's moves are not a knob: a talk has one per target and two more (наряд FIX-3 §7, seven targets —
     * nine moves); at nought the prompt is told `TURNS_LEFT: 0` and the role says goodbye itself — nothing cuts a
     * learner off mid-word. A «Не понял» is not one of these moves (переспросы нейтральны, кадр 37-12); it is paid for
     * out of the money instead. `minutes` is the HARD STOP of each kind of talk (and its «около N минут»): once the talk
     * has taken them, the next move is the role's last, `ended_reason: limit`.
     *
     * `cost_cap_usd` is what one talk may spend on the model and the voice together. Reaching it
     * makes the NEXT move the role's last (`ended_reason: limit`), it does not end the talk where
     * the learner stands.
     *
     * `model` is a `mini` class on purpose: a move has six seconds to come back and a talk is a
     * dozen of them. `timeout` is the seconds of its ONE attempt — a retry would double a wait the
     * learner is sitting through.
     *
     * No switch: the talk is part of every day (наряд ACC-1 §3 took down `PLAN_CONVERSATION_ENABLED`
     * with `plan_days.has_conversation`); a day with nothing to talk about has its sixth stage skipped.
     */
    'conversation' => [
        'minutes' => [
            'day' => (int) env('PLAN_CONVERSATION_MINUTES_DAY', 5),
            'rehearsal' => (int) env('PLAN_CONVERSATION_MINUTES_REHEARSAL', 6),
            'review' => (int) env('PLAN_CONVERSATION_MINUTES_REVIEW', 4),
        ],
        'cost_cap_usd' => (float) env('PLAN_CONVERSATION_COST_CAP_USD', 0.08),
        // «Повторить разговор» (наряд BACK-TAILS-2 §7): how many replays of a walked talk one day of the plan takes in one
        // calendar day of the learner — each is a model and a voice bought. Past it: 409 `plan_conversation_replay_limit`
        // with `retry_after_utc`, the learner's next midnight.
        'replays_per_day' => (int) env('PLAN_CONVERSATION_REPLAYS_PER_DAY', 3),
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

    /*
     * THE CANON OF THE ADMIN'S PLAN PAGE (наряд ADM-1 и доработка, «Что не так» и «Деньги»): a day ≈ $0.16 — generation
     * ≈ $0.08 (lesson, repairs, seam judge) plus voice ≈ $0.08 — a warning from 125 % of it ($0.20), an error from 150 %
     * ($0.24); the repairs at most 10 % of the day's generation (DECISIONS п. 323); the role's openings (`opens_target`)
     * are recorded since 23.09 — talks begun earlier are not judged on them. Read by the page only; nothing is stopped or
     * bought by it.
     */
    'inspection' => [
        'day_usd' => (float) env('PLAN_CANON_DAY_USD', 0.16),
        'generation_usd' => (float) env('PLAN_CANON_GENERATION_USD', 0.08),
        'voice_usd' => (float) env('PLAN_CANON_VOICE_USD', 0.08),
        'repair_share' => (float) env('PLAN_CANON_REPAIR_SHARE', 0.10),
        'warn_ratio' => (float) env('PLAN_CANON_WARN_RATIO', 1.25),
        'error_ratio' => (float) env('PLAN_CANON_ERROR_RATIO', 1.5),
        'openers_since' => env('PLAN_CANON_OPENERS_SINCE', '2026-09-23T00:00:00+00:00'),
    ],
];
