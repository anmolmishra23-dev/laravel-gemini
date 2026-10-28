<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Gemini API key from https://aistudio.google.com/apikey
    |
    */

    'api_key' => env('GEMINI_API_KEY'),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    |
    | Used when a request does not call ->model(). Any Gemini model id works.
    |
    */

    'models' => [
        'image' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
        'text' => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Options
    |--------------------------------------------------------------------------
    |
    | Image generation can take a while, so the timeout is generous. Requests
    | that fail with a connection error, 429 or 5xx are retried.
    |
    */

    'timeout' => (int) env('GEMINI_TIMEOUT', 120),

    'retry' => [
        'times' => (int) env('GEMINI_RETRY_TIMES', 3),
        'sleep' => (int) env('GEMINI_RETRY_SLEEP', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk generated images are saved to. Null uses your default disk.
    |
    */

    'disk' => env('GEMINI_DISK'),

];
