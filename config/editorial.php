<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI provider driver
    |--------------------------------------------------------------------------
    |
    | stub — deterministic offline provider (default in testing)
    | openai — production OpenAI adapter (fail-closed without API key)
    |
    */
    'ai' => [
        'driver' => env('EDITORIAL_AI_DRIVER', env('APP_ENV') === 'testing' ? 'stub' : 'openai'),
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'chat_path' => env('OPENAI_CHAT_PATH', '/chat/completions'),
            'model' => env('OPENAI_MODEL', 'gpt-5'),
            'temperature' => (float) env('OPENAI_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 2048),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 60),
            'retries' => (int) env('OPENAI_RETRIES', 1),
            'secret_key' => env('OPENAI_SECRET_KEY_NAME', 'openai_api_key'),
        ],
    ],
];
