<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Vector Embedding Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "openai" (OpenAI API or local Ollama)
    |
    */
    'default' => env('VECTOR_DRIVER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Vector Dimensions
    |--------------------------------------------------------------------------
    |
    | Dimensions expected by your database vector columns (e.g. 1536 for OpenAI small)
    |
    */
    'dimensions' => (int) env('VECTOR_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'openai' => [
            'api_key'    => env('OPENAI_API_KEY', ''),
            'model'      => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'base_url'   => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'dimensions' => (int) env('VECTOR_DIMENSIONS', 1536),
        ],
    ],
];
