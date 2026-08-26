<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers: "anthropic"
    |
    */
    'default' => env('AI_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'api_key'    => env('ANTHROPIC_API_KEY', ''),
            'model'      => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 8192),
            'timeout'    => (int) env('ANTHROPIC_TIMEOUT', 120),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Continuity Memory Settings
    |--------------------------------------------------------------------------
    |
    | Path to persistent project context memory file.
    |
    */
    'continuity' => [
        'enabled' => true,
        'path'    => base_path('.spinx/ai/continuity.json'),
    ],
];
