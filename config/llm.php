<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers: "anthropic", "openai"
    |
    */
    'default' => env('LLM_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | LLM Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'anthropic' => [
            'driver'  => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'model'   => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
        ],

        'openai' => [
            'driver'   => 'openai',
            'api_key'  => env('OPENAI_API_KEY', ''),
            'model'    => env('OPENAI_MODEL', 'gpt-4o'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
    ],
];
