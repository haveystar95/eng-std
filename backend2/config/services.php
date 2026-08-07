<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        // Accepted OAuth client IDs for verifying Google Sign-In ID tokens:
        // the iOS client id (from the mobile app) and, optionally, a web/server one.
        'client_ids' => array_values(array_filter([
            env('GOOGLE_IOS_CLIENT_ID'),
            env('GOOGLE_WEB_CLIENT_ID'),
        ])),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'generate_model' => env('OPENAI_GENERATE_MODEL', 'gpt-4o'),
        // Enriching a single bare term is a small task — the cheaper model is plenty.
        'enrich_model' => env('OPENAI_ENRICH_MODEL', 'gpt-4o-mini'),
        // Recapping a finished practice dialog is a tiny task — the cheap text model is plenty.
        'summary_model' => env('OPENAI_SUMMARY_MODEL', 'gpt-4o-mini'),
    ],

    'pexels' => [
        // Stock-image search for AI-generated collections (A3). Key from the Pexels dashboard.
        'key' => env('PEXELS_API_KEY'),
        // Politeness delay between image searches inside AttachImagesJob, ms. Pexels' free tier is
        // 200 req/hour; a ~15-term collection is well under, but spacing calls keeps us clear of bursts.
        'throttle_ms' => (int) env('PEXELS_THROTTLE_MS', 250),
        // Which outcome FakePexelsImageSearch returns when IMAGE_DRIVER=fake: found | not_found |
        // rate_limited | transient_error. Tests usually bind the fake directly instead.
        'fake_mode' => env('PEXELS_FAKE_MODE', 'found'),
    ],

    'generation' => [
        // 'openai' (default) or 'fake' (deterministic, no network — for local/dev/tests).
        'driver' => env('GENERATION_DRIVER', 'openai'),
        // 'pexels' (default) or 'fake' — the image-search adapter for AttachImagesJob.
        'image_driver' => env('IMAGE_DRIVER', 'pexels'),
    ],

    'practice' => [
        // Realtime conversation practice (premium). 'openai' (default) mints real ephemeral tokens
        // and summarises via OpenAI; 'fake' is deterministic and offline (tests / local dev).
        'driver' => env('PRACTICE_DRIVER', 'openai'),
        // Realtime engine + voice. Default is the cheaper "mini" realtime model (gpt-realtime-mini
        // is deprecated → 2.1-mini). Confirm the exact model name against the current OpenAI docs
        // before flipping production.
        'realtime_model' => env('PRACTICE_REALTIME_MODEL', 'gpt-realtime-2.1-mini'),
        // Input-audio transcription model — REQUIRED for the learner's speech to come back as
        // transcript events (input_audio_transcription.completed); without it coverage never lights up.
        'transcribe_model' => env('PRACTICE_REALTIME_TRANSCRIBE_MODEL', 'gpt-4o-mini-transcribe'),
        'voice' => env('PRACTICE_REALTIME_VOICE', 'alloy'),
        // Which versioned lesson prompt to render (Infrastructure/Prompt/practice_dialog.{v}.md).
        'prompt_version' => env('PRACTICE_PROMPT_VERSION', 'v2'),
        // Server-VAD turn detection — tuned so the model doesn't cut the learner off mid-sentence.
        'vad_silence_ms' => (int) env('PRACTICE_VAD_SILENCE_MS', 900),
        'vad_threshold' => (float) env('PRACTICE_VAD_THRESHOLD', 0.5),
        'vad_prefix_padding_ms' => (int) env('PRACTICE_VAD_PREFIX_PADDING_MS', 300),
        // The session duration guard: the ephemeral token expires after this many seconds.
        'dialog_ttl_seconds' => (int) env('PRACTICE_DIALOG_TTL_SECONDS', 200),
        // Per-user daily cap (premium-only feature; a flat cost guard, not a plan differentiator).
        'dialogs_per_day' => (int) env('PRACTICE_DIALOGS_PER_DAY', 5),
        // Upper bound on how many collection terms a lesson briefs as target words.
        'max_target_words' => (int) env('PRACTICE_MAX_TARGET_WORDS', 8),
    ],

];
