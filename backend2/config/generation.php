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
         * ГОЛОСА ЯЗЫКОВОГО ПАКЕТА — язык обучения → роль → пол (TTS-2). У сцены два человека разного пола; у
         * мужчины-собеседника свой голос, чтобы он не звучал как мужчина-ученик, женский голос у ролей один.
         *
         * Модель у всех голосов одна — `eleven_v3_conversational` (v3 Conversational, решение архитектора TTS-2): каждая
         * строка, и реплика собеседника, и реплика ученика, — свой вызов своим голосом. `stability` 0.5 — пресет
         * Natural v3; остальное — по умолчанию вендора. Смена модели, голоса или стабильности — другой ключ файла и новые
         * файлы, а не переигрывание старых (DECISIONS п. 248).
         *
         * Языка нет в таблице — голоса нет, и это не отказ: строки звучат системным синтезом.
         */
        'voices' => [
            'en' => [
                'partner' => [
                    // Выбор владельца (TTS-2).
                    'female' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_PARTNER_FEMALE', '4tRn1lSkEn13EVTuqb0g'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                    // Подобран по описанию библиотеки («casual, balanced», американский), не ушами: строка конфига,
                    // уши владельца могут её сменить (DECISIONS п. 249).
                    'male' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_PARTNER_MALE', 'EnjklPXGBMNldCJ7jqkE'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                ],
                'learner' => [
                    // Женский голос у ролей один — сцена всё равно двух полов.
                    'female' => [
                        'provider' => 'elevenlabs',
                        'model' => env('SPEECH_MODEL', 'eleven_v3_conversational'),
                        'voice' => env('SPEECH_VOICE_EN_LEARNER_FEMALE', '4tRn1lSkEn13EVTuqb0g'),
                        'stability' => (float) env('SPEECH_STABILITY', 0.5),
                    ],
                    // Выбор владельца (TTS-2).
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
