<?php

namespace AnmolMishra\Gemini\Responses;

class Usage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $thoughtTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly int $imageTokens = 0,
    ) {}

    public static function fromArray(array $usage): self
    {
        $imageTokens = collect($usage['candidatesTokensDetails'] ?? [])
            ->where('modality', 'IMAGE')
            ->sum('tokenCount');

        return new self(
            promptTokens: $usage['promptTokenCount'] ?? 0,
            outputTokens: $usage['candidatesTokenCount'] ?? 0,
            thoughtTokens: $usage['thoughtsTokenCount'] ?? 0,
            totalTokens: $usage['totalTokenCount'] ?? 0,
            imageTokens: $imageTokens,
        );
    }

    /**
     * Estimated cost in USD for a model, using config('gemini.pricing').
     */
    public function cost(string $model): ?float
    {
        $price = config('gemini.pricing', [])[$model] ?? null;

        if ($price === null) {
            return null;
        }

        // Thinking tokens are billed as text output.
        $textTokens = $this->outputTokens - $this->imageTokens + $this->thoughtTokens;

        $cost = $this->promptTokens * $price['input']
            + $textTokens * $price['output']
            + $this->imageTokens * ($price['image_output'] ?? $price['output']);

        return round($cost / 1_000_000, 6);
    }
}
