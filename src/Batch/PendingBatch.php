<?php

namespace AnmolMishra\Gemini\Batch;

use AnmolMishra\Gemini\GeminiClient;
use AnmolMishra\Gemini\Requests\GenerationRequest;

class PendingBatch
{
    protected int $concurrency = 5;

    /**
     * @param  array<array-key, GenerationRequest>  $requests
     */
    public function __construct(
        protected GeminiClient $client,
        protected array $requests,
    ) {}

    /**
     * How many requests run at the same time. Keep this within your rate limits.
     */
    public function concurrency(int $concurrency): static
    {
        $this->concurrency = max(1, $concurrency);

        return $this;
    }

    /**
     * Run every request. Failed requests do not stop the batch; they are
     * returned as exceptions in the results.
     */
    public function generate(): BatchResults
    {
        $results = [];

        foreach (array_chunk($this->requests, $this->concurrency, preserve_keys: true) as $chunk) {
            $outcomes = $this->client->generateContentMany(array_map(
                fn (GenerationRequest $request) => [$request->getModel(), $request->toPayload()],
                $chunk,
            ));

            foreach ($outcomes as $key => $outcome) {
                $results[$key] = is_array($outcome) ? rescue(
                    fn () => $chunk[$key]->toResponse($outcome),
                    fn ($e) => $e,
                    report: false,
                ) : $outcome;
            }
        }

        return new BatchResults($results);
    }
}
