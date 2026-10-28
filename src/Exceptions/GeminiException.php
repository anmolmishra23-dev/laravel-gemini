<?php

namespace AnmolMishra\Gemini\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class GeminiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function missingApiKey(): self
    {
        return new self('Gemini API key is missing. Set GEMINI_API_KEY in your .env file.');
    }

    public static function fromResponse(Response $response, string $model): self
    {
        $message = $response->json('error.message') ?? $response->body();

        return new self("Gemini API error [{$model}] ({$response->status()}): {$message}", $response->status(), $response);
    }

    public static function connectionFailed(string $model, Throwable $previous): self
    {
        return new self("Could not connect to the Gemini API [{$model}]: {$previous->getMessage()}", previous: $previous);
    }

    public static function promptBlocked(string $reason, Response $response): self
    {
        return new self("Gemini blocked the prompt (reason: {$reason}).", $response->status(), $response);
    }

    public static function noImageReturned(?string $finishReason, ?string $text): self
    {
        $message = 'Gemini did not return an image (finish reason: '.($finishReason ?? 'unknown').').';

        if (filled($text)) {
            $message .= " Model said: {$text}";
        }

        return new self($message);
    }

    public static function noVideoReturned(?string $text): self
    {
        $message = 'Gemini did not return a video.';

        if (filled($text)) {
            $message .= " Model said: {$text}";
        }

        return new self($message);
    }

    public static function fileNotReady(string $id, string $state): self
    {
        return new self("Gemini file [{$id}] is not ready to download (state: {$state}).");
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
