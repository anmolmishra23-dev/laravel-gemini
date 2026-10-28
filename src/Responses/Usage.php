<?php

namespace AnmolMishra\Gemini\Responses;

class Usage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $thoughtTokens = 0,
        public readonly int $totalTokens = 0,
    ) {}

    public static function fromArray(array $usage): self
    {
        return new self(
            promptTokens: $usage['promptTokenCount'] ?? 0,
            outputTokens: $usage['candidatesTokenCount'] ?? 0,
            thoughtTokens: $usage['thoughtsTokenCount'] ?? 0,
            totalTokens: $usage['totalTokenCount'] ?? 0,
        );
    }
}
