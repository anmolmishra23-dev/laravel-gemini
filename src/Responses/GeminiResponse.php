<?php

namespace AnmolMishra\Gemini\Responses;

abstract class GeminiResponse
{
    /**
     * @param  GeneratedImage[]  $images
     */
    final public function __construct(
        public readonly ?string $text,
        public readonly ?string $finishReason,
        public readonly Usage $usage,
        public readonly array $raw,
        protected array $images = [],
    ) {}

    public static function fromArray(array $data): static
    {
        $text = null;
        $images = [];

        foreach (data_get($data, 'candidates.0.content.parts', []) as $part) {
            // Skip the model's internal reasoning.
            if (! empty($part['thought'])) {
                continue;
            }

            if (isset($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['inlineData']['data'])) {
                $images[] = new GeneratedImage($part['inlineData']['data'], $part['inlineData']['mimeType'] ?? 'image/png');
            }
        }

        return new static(
            text: $text === null ? null : trim($text),
            finishReason: data_get($data, 'candidates.0.finishReason'),
            usage: Usage::fromArray($data['usageMetadata'] ?? []),
            raw: $data,
            images: $images,
        );
    }
}
