<?php

namespace AnmolMishra\Gemini\Batch;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Responses\GeminiResponse;
use AnmolMishra\Gemini\Responses\ImageResponse;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<array-key, GeminiResponse|GeminiException>
 */
class BatchResults implements Countable, IteratorAggregate
{
    /**
     * @param  array<array-key, GeminiResponse|GeminiException>  $results  keyed like the requests
     */
    public function __construct(protected array $results) {}

    public function get(string|int $key): GeminiResponse|GeminiException|null
    {
        return $this->results[$key] ?? null;
    }

    /**
     * @return array<array-key, GeminiResponse|GeminiException>
     */
    public function all(): array
    {
        return $this->results;
    }

    /**
     * @return array<array-key, GeminiResponse>
     */
    public function successful(): array
    {
        return array_filter($this->results, fn ($result) => $result instanceof GeminiResponse);
    }

    /**
     * @return array<array-key, GeminiException>
     */
    public function failed(): array
    {
        return array_filter($this->results, fn ($result) => $result instanceof GeminiException);
    }

    public function hasFailures(): bool
    {
        return $this->failed() !== [];
    }

    /**
     * Store every generated image and return the paths, keyed like the requests.
     *
     * @return array<array-key, string>
     */
    public function store(string $directory = '', ?string $disk = null): array
    {
        return array_map(
            fn (ImageResponse $response) => $response->store($directory, $disk),
            array_filter($this->results, fn ($result) => $result instanceof ImageResponse),
        );
    }

    /**
     * Estimated total cost in USD of the successful requests with a configured price.
     */
    public function totalCost(): float
    {
        return round(array_sum(array_map(fn (GeminiResponse $response) => $response->cost() ?? 0, $this->successful())), 6);
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
