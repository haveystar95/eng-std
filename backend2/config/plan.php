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
        // Per-call vendor timeout, seconds. Both calls are asynchronous jobs the client polls.
        'plan_timeout' => (int) env('PLAN_BUILDER_TIMEOUT', 90),
        'lesson_timeout' => (int) env('PLAN_LESSON_TIMEOUT', 90),
    ],

    // A build that started and never came back in this many seconds counts as dead: the client
    // sees `failed` and may ask for a retry. Two attempts × the timeout, plus the writes.
    'build_stale_seconds' => (int) env('PLAN_BUILD_STALE_SECONDS', 240),

    // What a lesson orders, per level. PHRASES_COUNT is never larger than DIALOGUE_COUNT — the
    // prompt's own rule, and PlanConfig clamps it.
    'counts' => [
        'beginner' => ['phrases' => 6, 'vocabulary' => 8, 'dialogue' => 8],
        'intermediate' => ['phrases' => 6, 'vocabulary' => 8, 'dialogue' => 8],
    ],

    /*
     * THE CHECKS (docs/plan-v2.md §5): `observe` counts and keeps, `drop` erases the broken mark
     * or field, `gate` refuses the answer and buys one retry. Every check ships as `observe`; the
     * admin panel shows the counters per prompt version, and a mode is switched HERE, after real
     * lessons, never in code. Checks marked «observe навсегда» in the canon ignore this table.
     */
    'checks' => [
        'lesson' => [
            'counts' => env('PLAN_CHECK_COUNTS', 'observe'),
            'exchange_shape' => env('PLAN_CHECK_EXCHANGE_SHAPE', 'observe'),
            'second_message_question' => env('PLAN_CHECK_SECOND_MESSAGE_QUESTION', 'observe'),
            'speaking_key_substring' => env('PLAN_CHECK_SPEAKING_KEY_SUBSTRING', 'observe'),
            'pronunciation_script' => env('PLAN_CHECK_PRONUNCIATION_SCRIPT', 'observe'),
            'variant_length' => env('PLAN_CHECK_VARIANT_LENGTH', 'observe'),
            'vocabulary_id_absent' => env('PLAN_CHECK_VOCABULARY_ID_ABSENT', 'observe'),
            'phrase_id_absent' => env('PLAN_CHECK_PHRASE_ID_ABSENT', 'observe'),
            'phrase_unused' => env('PLAN_CHECK_PHRASE_UNUSED', 'observe'),
            'vocabulary_contained' => env('PLAN_CHECK_VOCABULARY_CONTAINED', 'observe'),
        ],
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
];
