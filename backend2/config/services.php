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

];
