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
        'video' => env('GEMINI_VIDEO_MODEL', 'gemini-omni-1.1-flash'),
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
    | Video
    |--------------------------------------------------------------------------
    |
    | Video generation takes minutes. Generated videos are downloaded once the
    | file is ready; poll_interval and poll_timeout are in seconds.
    |
    */

    'video' => [
        'timeout' => (int) env('GEMINI_VIDEO_TIMEOUT', 600),
        'poll_interval' => 5,
        'poll_timeout' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk generated images and videos are saved to. Any disk from
    | config/filesystems.php works: local, s3, gcs, r2, azure, ftp and more.
    | Null uses your default disk.
    |
    */

    'disk' => env('GEMINI_DISK'),

    // "public" makes saved files viewable by anyone with the URL, "private"
    // keeps them private. Null uses each disk's own default.
    'visibility' => env('GEMINI_VISIBILITY'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Where queued generations run. Make sure the connection's retry_after is
    | longer than the job timeout (300 seconds for images, 900 for videos).
    |
    */

    'queue' => [
        'connection' => env('GEMINI_QUEUE_CONNECTION'),
        'name' => env('GEMINI_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | USD per 1 million tokens, used by $response->cost(). Standard paid tier
    | prices from https://ai.google.dev/gemini-api/docs/pricing (October 2026).
    | Prices change, so publish this config and keep it up to date. Pro
    | models cost more above 200k prompt tokens; these are the lower prices.
    |
    */

    'pricing' => [
        // Image models
        'gemini-nano-banana-2.1' => ['input' => 1.50, 'output' => 7.50, 'image_output' => 30.00],
        'gemini-3.1-flash-image' => ['input' => 0.50, 'output' => 3.00, 'image_output' => 60.00],
        'gemini-3.1-flash-lite-image' => ['input' => 0.25, 'output' => 1.50, 'image_output' => 30.00],
        'gemini-3-pro-image' => ['input' => 2.00, 'output' => 12.00, 'image_output' => 120.00],

        // Text models
        'gemini-3.8-flash' => ['input' => 0.75, 'output' => 3.75],
        'gemini-3.6-flash' => ['input' => 0.75, 'output' => 3.75],
        'gemini-3.5-flash-lite' => ['input' => 0.30, 'output' => 2.50],
        'gemini-3.1-pro-preview' => ['input' => 2.00, 'output' => 12.00],
        'gemini-3.1-flash-lite' => ['input' => 0.25, 'output' => 1.50],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
    ],

];
