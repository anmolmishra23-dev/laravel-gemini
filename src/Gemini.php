<?php

namespace AnmolMishra\Gemini;

use AnmolMishra\Gemini\Requests\ImageRequest;
use AnmolMishra\Gemini\Requests\TextRequest;

class Gemini
{
    public function __construct(
        protected GeminiClient $client,
        protected array $config = [],
    ) {}

    /**
     * Start an image generation (or image editing) request.
     */
    public function image(?string $prompt = null): ImageRequest
    {
        $request = new ImageRequest($this->client, $this->config['models']['image'] ?? 'gemini-3.1-flash-image');

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    /**
     * Start a text generation request.
     */
    public function text(?string $prompt = null): TextRequest
    {
        $request = new TextRequest($this->client, $this->config['models']['text'] ?? 'gemini-3.5-flash');

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    /**
     * The low-level client, for sending raw generateContent payloads.
     */
    public function client(): GeminiClient
    {
        return $this->client;
    }
}
