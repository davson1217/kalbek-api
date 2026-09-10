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
        'transcription_model' => env('KALBEK_TRANSCRIPTION_MODEL', 'openai/gpt-4o-mini-transcribe'),
        'tts_model' => env('KALBEK_TTS_MODEL', 'gpt-4o-mini-tts'),
        'tts_voice_options' => [
            'gemini' => [
                'Zephyr' => 'Zephyr - bright',
                'Puck' => 'Puck - upbeat',
                'Charon' => 'Charon - informative',
                'Kore' => 'Kore - firm',
                'Fenrir' => 'Fenrir - excitable',
                'Leda' => 'Leda - youthful',
                'Orus' => 'Orus - firm',
                'Aoede' => 'Aoede - breezy',
                'Callirrhoe' => 'Callirrhoe - easy-going',
                'Autonoe' => 'Autonoe - bright',
                'Enceladus' => 'Enceladus - breathy',
                'Iapetus' => 'Iapetus - clear',
                'Umbriel' => 'Umbriel - easy-going',
                'Algieba' => 'Algieba - smooth',
                'Despina' => 'Despina - smooth',
                'Erinome' => 'Erinome - clear',
                'Algenib' => 'Algenib - gravelly',
                'Rasalgethi' => 'Rasalgethi - informative',
                'Laomedeia' => 'Laomedeia - upbeat',
                'Achernar' => 'Achernar - soft',
                'Alnilam' => 'Alnilam - firm',
                'Schedar' => 'Schedar - even',
                'Gacrux' => 'Gacrux - mature',
                'Pulcherrima' => 'Pulcherrima - forward',
                'Achird' => 'Achird - friendly',
                'Zubenelgenubi' => 'Zubenelgenubi - casual',
                'Vindemiatrix' => 'Vindemiatrix - gentle',
                'Sadachbia' => 'Sadachbia - lively',
                'Sadaltager' => 'Sadaltager - knowledgeable',
                'Sulafat' => 'Sulafat - warm',
            ],
            'openai' => [
                'alloy' => 'Alloy',
                'ash' => 'Ash',
                'ballad' => 'Ballad',
                'coral' => 'Coral',
                'echo' => 'Echo',
                'fable' => 'Fable',
                'onyx' => 'Onyx',
                'nova' => 'Nova',
                'sage' => 'Sage',
                'shimmer' => 'Shimmer',
                'verse' => 'Verse',
                'marin' => 'Marin',
                'cedar' => 'Cedar',
            ],
        ],
        'tts_pcm_sample_rate' => env('KALBEK_TTS_PCM_SAMPLE_RATE', 24000),
        'tts_metadata_cache_ttl_seconds' => env('KALBEK_TTS_METADATA_CACHE_TTL_SECONDS', 86400),
        'tts_generation_lock_seconds' => env('KALBEK_TTS_GENERATION_LOCK_SECONDS', 60),
        'tts_generation_lock_wait_seconds' => env('KALBEK_TTS_GENERATION_LOCK_WAIT_SECONDS', 50),
        'generated_audio_disk' => env('KALBEK_GENERATED_AUDIO_DISK', 'local'),
        'generated_audio_path' => env('KALBEK_GENERATED_AUDIO_PATH', 'generated-audio'),
        'generated_audio_fallback_disk' => env('KALBEK_GENERATED_AUDIO_FALLBACK_DISK', 'local'),
        'generated_audio_allow_fallback' => env('KALBEK_GENERATED_AUDIO_ALLOW_FALLBACK', false),
        'fake_speech_transcript' => env('KALBEK_FAKE_SPEECH_TRANSCRIPT', 'Ar turite maisto?'),
        'fake_speech_pass' => env('KALBEK_FAKE_SPEECH_PASS', true),
        'fake_speech_feedback' => env('KALBEK_FAKE_SPEECH_FEEDBACK', 'Dev AI mode: speech accepted without calling an AI provider.'),
    ],

];
