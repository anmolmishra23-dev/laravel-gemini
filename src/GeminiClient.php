<?php

namespace AnmolMishra\Gemini;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiClient
{
    protected const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(protected array $config) {}

    /**
     * Send a raw payload to the generateContent endpoint and return the decoded response.
     *
     * @throws GeminiException
     */
    public function generateContent(string $model, array $payload): array
    {
        if (blank($this->config['api_key'] ?? null)) {
            throw GeminiException::missingApiKey();
        }

        try {
            $response = $this->request()->post($this->url($model), $payload);
        } catch (ConnectionException $e) {
            throw GeminiException::connectionFailed($model, $e);
        }

        if ($response->failed()) {
            throw GeminiException::fromResponse($response, $model);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new GeminiException("Gemini API returned invalid JSON [{$model}].", $response->status(), $response);
        }

        if ($reason = data_get($data, 'promptFeedback.blockReason')) {
            throw GeminiException::promptBlocked($reason, $response);
        }

        return $data;
    }

    protected function request(): PendingRequest
    {
        $request = Http::withHeaders(['x-goog-api-key' => $this->config['api_key']])
            ->acceptJson()
            ->asJson()
            ->timeout($this->config['timeout'] ?? 120);

        $times = $this->config['retry']['times'] ?? 1;

        if ($times > 1) {
            $request->retry(
                $times,
                $this->config['retry']['sleep'] ?? 1000,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), self::RETRYABLE_STATUSES)),
                throw: false,
            );
        }

        return $request;
    }

    protected function url(string $model): string
    {
        $baseUrl = rtrim($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');

        return "{$baseUrl}/models/{$model}:generateContent";
    }
}
