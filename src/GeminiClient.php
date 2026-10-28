<?php

namespace AnmolMishra\Gemini;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
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
        return $this->send($model, fn () => $this->request()->post($this->url($model), $payload));
    }

    /**
     * Send several generateContent payloads concurrently. Failures are returned
     * as exceptions in the result instead of being thrown.
     *
     * @param  array<array-key, array{0: string, 1: array}>  $requests  [model, payload] keyed by your own keys
     * @return array<array-key, array|GeminiException>
     */
    public function generateContentMany(array $requests): array
    {
        $this->ensureApiKey();

        $responses = Http::pool(function (Pool $pool) use ($requests) {
            foreach ($requests as $key => [$model, $payload]) {
                $this->configure($pool->as((string) $key))->post($this->url($model), $payload);
            }
        });

        $results = [];

        foreach ($requests as $key => [$model]) {
            $response = $responses[$key] ?? null;

            try {
                $results[$key] = $response instanceof Response
                    ? $this->decode($response, $model)
                    : throw GeminiException::connectionFailed($model, $response ?? new ConnectionException('No response.'));
            } catch (GeminiException $e) {
                $results[$key] = $e;
            }
        }

        return $results;
    }

    /**
     * Create an interaction (used for video generation) and return the decoded response.
     *
     * @throws GeminiException
     */
    public function createInteraction(array $payload): array
    {
        $model = $payload['model'] ?? 'unknown';

        return $this->send($model, fn () => $this->request($this->config['video']['timeout'] ?? 600)
            ->post($this->baseUrl().'/interactions', $payload));
    }

    /**
     * Wait for an uploaded or generated file to become active, then download it.
     *
     * @throws GeminiException
     */
    public function downloadFile(string $uri): string
    {
        $id = Str::of($uri)->before('?')->rtrim('/')->afterLast('/')->before(':')->toString();
        $interval = $this->config['video']['poll_interval'] ?? 5;
        $attempts = max(1, intdiv($this->config['video']['poll_timeout'] ?? 600, max(1, $interval)));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $file = $this->send("files/{$id}", fn () => $this->request()->get($this->baseUrl()."/files/{$id}"));
            $state = $file['state'] ?? 'ACTIVE';

            if ($state === 'ACTIVE') {
                break;
            }

            if ($state === 'FAILED' || $attempt === $attempts) {
                throw GeminiException::fileNotReady($id, $state);
            }

            Sleep::for($interval)->seconds();
        }

        try {
            $response = $this->request($this->config['video']['timeout'] ?? 600)
                ->accept('*/*')
                ->get($this->baseUrl()."/files/{$id}:download", ['alt' => 'media']);
        } catch (ConnectionException $e) {
            throw GeminiException::connectionFailed("files/{$id}", $e);
        }

        if ($response->failed()) {
            throw GeminiException::fromResponse($response, "files/{$id}");
        }

        return $response->body();
    }

    /**
     * @param  callable(): Response  $send
     */
    protected function send(string $model, callable $send): array
    {
        $this->ensureApiKey();

        try {
            $response = $send();
        } catch (ConnectionException $e) {
            throw GeminiException::connectionFailed($model, $e);
        }

        return $this->decode($response, $model);
    }

    protected function decode(Response $response, string $model): array
    {
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

    protected function request(?int $timeout = null): PendingRequest
    {
        $request = $this->configure(Http::createPendingRequest(), $timeout);
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

    protected function configure(PendingRequest $request, ?int $timeout = null): PendingRequest
    {
        return $request->withHeaders(['x-goog-api-key' => $this->config['api_key']])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? $this->config['timeout'] ?? 120);
    }

    protected function ensureApiKey(): void
    {
        if (blank($this->config['api_key'] ?? null)) {
            throw GeminiException::missingApiKey();
        }
    }

    protected function url(string $model): string
    {
        return $this->baseUrl()."/models/{$model}:generateContent";
    }

    protected function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
    }
}
