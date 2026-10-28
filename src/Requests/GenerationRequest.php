<?php

namespace AnmolMishra\Gemini\Requests;

use AnmolMishra\Gemini\Concerns\HasImageInputs;
use AnmolMishra\Gemini\Concerns\SerializesWithoutClient;
use AnmolMishra\Gemini\GeminiClient;
use AnmolMishra\Gemini\Responses\GeminiResponse;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;

abstract class GenerationRequest
{
    use Conditionable;
    use HasImageInputs;
    use SerializesWithoutClient;

    protected ?string $prompt = null;

    protected ?string $system = null;

    protected array $generationConfig = [];

    public function __construct(
        protected GeminiClient $client,
        protected string $model,
    ) {}

    /**
     * Turn a raw generateContent response into this request's response type.
     */
    abstract public function toResponse(array $data): GeminiResponse;

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function prompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function system(string $instruction): static
    {
        $this->system = $instruction;

        return $this;
    }

    public function temperature(float $temperature): static
    {
        $this->generationConfig['temperature'] = $temperature;

        return $this;
    }

    /**
     * Merge raw generationConfig options, for anything without a dedicated method.
     */
    public function withConfig(array $config): static
    {
        $this->generationConfig = array_replace_recursive($this->generationConfig, $config);

        return $this;
    }

    /**
     * Build the generateContent payload without sending it.
     */
    public function toPayload(): array
    {
        return $this->buildPayload([$this->userTurn()]);
    }

    protected function call(): array
    {
        return $this->client->generateContent($this->model, $this->toPayload());
    }

    protected function userTurn(): array
    {
        if ($this->prompt === null && $this->images === []) {
            throw new InvalidArgumentException('A Gemini request needs a prompt or at least one image.');
        }

        // Gemini recommends placing images before the text prompt.
        $parts = array_map(
            fn (array $image) => ['inlineData' => ['mimeType' => $image['mimeType'], 'data' => $image['data']]],
            $this->resolveImages(),
        );

        if ($this->prompt !== null) {
            $parts[] = ['text' => $this->prompt];
        }

        return ['role' => 'user', 'parts' => $parts];
    }

    protected function buildPayload(array $contents): array
    {
        $payload = ['contents' => $contents];

        if ($this->system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $this->system]]];
        }

        $config = array_replace_recursive($this->defaultGenerationConfig(), $this->generationConfig);

        if ($config !== []) {
            $payload['generationConfig'] = $config;
        }

        return $payload;
    }

    protected function defaultGenerationConfig(): array
    {
        return [];
    }
}
