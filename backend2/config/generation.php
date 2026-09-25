<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ОЗВУЧКА ПЛАНА (наряды TTS-1, DAY-UI-3, TTS-2)
    |--------------------------------------------------------------------------
    |
    | Сервер озвучивает всё, что звучит в дне: реплики собеседника и ученика, фразы, фразы с наполнениями,
    | слова — голосами ElevenLabs, заранее, и файлы лежат на приватном диске (`docs/plan-v2.md` §7).
    | Системный синтез телефона — временная замена, пока файла нет.
    |
    */
    'speech' => [
        /*
         * ТУМБЛЕР ТРУБЫ. Выключено — ни одного обращения к вендору: день собирается, строки звучат
         * системным голосом телефона. Новый продукт рождается выключенным (DECISIONS п. 32) — и этот
         * особенно, потому что за него платят за каждый символ.
         */
        'enabled' => (bool) env('SPEECH_ENABLED', false),

        // 'elevenlabs' | 'fake'. Вендор один (TTS-2); `fake` — двойник для тестов и офлайна.
        'driver' => env('SPEECH_DRIVER', 'elevenlabs'),

        // Куда ложатся файлы. Приватный диск: раздаёт их маршрут плана под тем же токеном, что и остальной API.
        'disk' => env('SPEECH_DISK', 'local'),

        // Таймаут одного вызова — одна строка.
        'timeout' => (int) env('SPEECH_TIMEOUT', 60),

        // Одновременных запросов на старте раунда; дальше адаптер берёт число из заголовка вендора
        // `maximum-concurrent-requests` (Starter — 3).
        'concurrency' => (int) env('SPEECH_CONCURRENCY', 3),

        // Цена тысячи кредитов по тарифу аккаунта — во что обходится `character-cost` ответа. Starter: $6 за 30 000.
        'usd_per_thousand_credits' => (float) env('SPEECH_USD_PER_THOUSAND_CREDITS', 0.20),

        /*
         * ПРЕДОХРАНИТЕЛЬ. Остаток кредитов аккаунта меньше этой доли лимита — очередь голоса не покупает и пишет в
         * лог. Остаток — по API аккаунта (`/v1/user/subscription`); если вендор не ответил — по счётчику
         * `plan_line_audios.credits` с начала месяца против `monthly_credits` (Starter — 30 000).
         */
        'fuse_share' => 0.10,
        'monthly_credits' => (int) env('SPEECH_MONTHLY_CREDITS', 30000),

        /*
         * КАП НА ЗАПУСК. Больше этого числа кредитов один запуск не покупает: одна задача озвучки сцены или один запуск
         * `plan:speak-backfill`. Перед каждой сценой купленное за запуск складывается с оценкой сцены (символы строк по
         * тарифу модели); не помещается — стоп, ничего из сцены не куплено, письмо в лог (решение архитектора TTS-2).
         */
        'job_credits_cap' => (int) env('SPEECH_JOB_CREDITS_CAP', 3000),

        /*
         * БАЗЫ БЕЗ АВТООЗВУЧКИ. Здесь новые дни не озвучиваются сами, а `plan:speak-backfill` без `--plan` ничего не
         * покупает: голос — только по явному `--plan` (решение архитектора TTS-2, e2e-стенд исключён навсегда).
         */
        'named_plans_only_databases' => ['wordtrainer_e2e_test'],

        /*
         * ГОЛОСА ЯЗЫКОВОГО ПАКЕТА — язык обучения → роль → пол (TTS-2). Голоса ролей в сцене всегда разные, какого бы
         * пола ни была каждая роль: у собеседника и у ученика свой женский и свой мужской голос — четыре разных id.
         * Четыре голоса ниже Ден послушал и утвердил 15.09; сменить голос — одна строка `SPEECH_VOICE_EN_*` в `.env`,
         * перезапуск horizon и переозвучка по команде (`plan:speak-backfill --plan=… --drop-unread`, цена — до покупки).
         *
         * Модель у всех голосов одна — `eleven_v3_conversational` (v3 Conversational, решение архитектора TTS-2): каждая
         * строка, и реплика собеседника, и реплика ученика, — свой вызов своим голосом. `stability` 0.5 — пресет
         * Natural v3; темп — обычный у всех голосов (замедление «Повтори вслух» — забота клиента); остальное — по
         * умолчанию вендора. Смена модели, голоса или стабильности — другой ключ файла и новые файлы, а не переигрывание
         * старых (DECISIONS п. 248).
         *
         * Языка нет в таблице — голоса нет, и это не отказ: строки звучат системным синтезом.
         *
         * У собеседника — ВТОРОЙ голос каждого пола (`female_2`, `male_2`; наряд FIX-4c §1): голос сцены закрепляется за
         * ней один раз, при приёме её урока (`plan_scenes.partner_voice_id`), и сцены одного пола по порядку плана
         * чередуют голос 1 и 2 — две соседние сцены одного пола всегда звучат разными людьми. Вторые голоса Ден выбрал по
         * образцам 25.09 (`docs/research/fix-4c/voices/`): Maisie и Caleb.
         */
        'voices' => [
            'en' => [
                'partner' => [
                    'female' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_PARTNER_FEMALE', '4NejU5DwQjevnR6mh3mb'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                    'female_2' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_PARTNER_FEMALE_2', 'QtY3JBOUKEB5xzrRfOKc'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                    'male' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_PARTNER_MALE', 'EnjklPXGBMNldCJ7jqkE'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                    'male_2' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_PARTNER_MALE_2', 'AaOhDHYJ1XLZk74lXhdE'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                ],
                'learner' => [
                    'female' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_LEARNER_FEMALE', 'Nhs7eitvQWFTQBsf0yiT'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                    'male' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_LEARNER_MALE', 'TWutjvRaJqAX89preB4e'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                ],
            ],
        ],
    ],
];
