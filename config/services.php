<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
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
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'kalbek' => [
        'frontend_url' => env('KALBEK_FRONTEND_URL', 'http://localhost:3000'),
        'ai_mode' => env('KALBEK_AI_MODE', 'live'),
        'tts_model' => env('KALBEK_TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_pcm_sample_rate' => env('KALBEK_TTS_PCM_SAMPLE_RATE', 24000),
        'fake_speech_transcript' => env('KALBEK_FAKE_SPEECH_TRANSCRIPT', 'Ar turite maisto?'),
        'fake_speech_pass' => env('KALBEK_FAKE_SPEECH_PASS', true),
        'fake_speech_feedback' => env('KALBEK_FAKE_SPEECH_FEEDBACK', 'Dev AI mode: speech accepted without calling an AI provider.'),
    ],

];
