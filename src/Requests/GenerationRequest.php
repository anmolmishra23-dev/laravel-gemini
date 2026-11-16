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

    /**
     * Request fields that sit next to generationConfig rather than inside it.
     */
    protected const TOP_LEVEL_FIELDS = ['safetySettings', 'tools', 'toolConfig', 'cachedContent', 'systemInstruction'];

    protected ?string $prompt = null;

    protected ?string $system = null;

    protected array $generationConfig = [];

    protected array $payload = [];

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
     *
     * Top-level request fields such as safetySettings and tools are moved out of
     * generationConfig to where the API expects them.
     */
    public function withConfig(array $config): static
    {
        $topLevel = array_intersect_key($config, array_flip(self::TOP_LEVEL_FIELDS));

        $this->generationConfig = array_replace_recursive($this->generationConfig, array_diff_key($config, $topLevel));

        return $this->withPayload($topLevel);
    }

    /**
     * Merge raw fields into the top level of the request, next to contents and generationConfig.
     */
    public function withPayload(array $fields): static
    {
        $this->payload = array_replace($this->payload, $fields);

        return $this;
    }

    /**
     * Change how strictly content is blocked. Pass a map of category to threshold,
     * e.g. ['HARM_CATEGORY_HARASSMENT' => 'BLOCK_ONLY_HIGH'], or the API's list format.
     */
    public function safetySettings(array $settings): static
    {
        if (! array_is_list($settings)) {
            $settings = array_map(
                fn (string $category, string $threshold) => ['category' => $category, 'threshold' => $threshold],
                array_keys($settings),
                $settings,
            );
        }

        return $this->withPayload(['safetySettings' => $settings]);
    }

    /**
     * Tools the model can use, e.g. [['googleSearch' => (object) []]].
     */
    public function tools(array $tools): static
    {
        return $this->withPayload(['tools' => $tools]);
    }

    public function toolConfig(array $config): static
    {
        return $this->withPayload(['toolConfig' => $config]);
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

        $payload = array_replace($payload, $this->payload);

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
