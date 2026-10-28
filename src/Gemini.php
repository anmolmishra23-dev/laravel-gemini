<?php

namespace AnmolMishra\Gemini;

use AnmolMishra\Gemini\Batch\PendingBatch;
use AnmolMishra\Gemini\Requests\GenerationRequest;
use AnmolMishra\Gemini\Requests\ImageChat;
use AnmolMishra\Gemini\Requests\ImageRequest;
use AnmolMishra\Gemini\Requests\TextRequest;
use AnmolMishra\Gemini\Requests\VideoRequest;

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
     * Start a multi-turn conversation that keeps editing the same image.
     */
    public function imageChat(): ImageChat
    {
        return new ImageChat($this->client, $this->config['models']['image'] ?? 'gemini-3.1-flash-image');
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
     * Start a video generation request.
     */
    public function video(?string $prompt = null): VideoRequest
    {
        $request = new VideoRequest($this->client, $this->config['models']['video'] ?? 'gemini-omni-1.1-flash');

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    /**
     * Run many image or text requests concurrently. Plain strings become image prompts.
     *
     * @param  array<array-key, GenerationRequest|string>  $requests
     */
    public function batch(array $requests): PendingBatch
    {
        return new PendingBatch($this->client, array_map(
            fn (GenerationRequest|string $request) => is_string($request) ? $this->image($request) : $request,
            $requests,
        ));
    }

    /**
     * The low-level client, for sending raw payloads.
     */
    public function client(): GeminiClient
    {
        return $this->client;
    }
}
