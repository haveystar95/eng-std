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
        // Accepted OAuth client IDs for verifying Google Sign-In ID tokens.
        // iOS client id (from the mobile app) and, optionally, a web/server one.
        'client_ids' => array_filter([
            env('GOOGLE_IOS_CLIENT_ID'),
            env('GOOGLE_WEB_CLIENT_ID'),
        ]),
    ],

    'ai' => [
        // Which LLM backend to use: 'claude' or 'ollama'.
        'provider' => env('AI_PROVIDER', 'claude'),
    ],

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'generate_model' => env('CLAUDE_GENERATE_MODEL', 'claude-sonnet-5'),
        'check_model' => env('CLAUDE_CHECK_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'ollama' => [
        // Reachable from the app container as the compose service name.
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:3b'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'generate_model' => env('OPENAI_GENERATE_MODEL', 'gpt-4o'),
        'check_model' => env('OPENAI_CHECK_MODEL', 'gpt-4o-mini'),
    ],

];
