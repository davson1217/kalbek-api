<?php

return [
    'trial_days' => (int) env('KALBEK_TRIAL_DAYS', 3),
    'grace_days' => (int) env('KALBEK_GRACE_DAYS', 7),
    'free_scenario_limit' => (int) env('KALBEK_FREE_SCENARIO_LIMIT', 2),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300),
        'monthly_price_id' => env('STRIPE_MONTHLY_PRICE_ID'),
        'annual_price_id' => env('STRIPE_ANNUAL_PRICE_ID'),
        'success_url' => env('STRIPE_SUCCESS_URL', env('KALBEK_FRONTEND_URL', 'http://localhost:3000').'/profile'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('KALBEK_FRONTEND_URL', 'http://localhost:3000').'/profile'),
    ],
];
