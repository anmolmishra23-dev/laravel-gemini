<?php

namespace AnmolMishra\Gemini\Requests;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Responses\ImageResponse;

class ImageRequest extends GenerationRequest
{
    protected ?string $aspectRatio = null;

    protected ?string $size = null;

    protected bool $withText = false;

    /**
     * e.g. "1:1", "16:9", "9:16", "4:3", "3:4".
     */
    public function aspectRatio(string $ratio): static
    {
        $this->aspectRatio = $ratio;

        return $this;
    }

    /**
     * Output resolution, e.g. "1K", "2K", "4K" (supported models only).
     */
    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Ask the model to return text alongside the image.
     */
    public function withText(bool $withText = true): static
    {
        $this->withText = $withText;

        return $this;
    }

    /**
     * @throws GeminiException
     */
    public function generate(): ImageResponse
    {
        $response = ImageResponse::fromArray($this->send());

        if ($response->images() === []) {
            throw GeminiException::noImageReturned($response->finishReason, $response->text);
        }

        return $response;
    }

    protected function defaultGenerationConfig(): array
    {
        $config = ['responseModalities' => $this->withText ? ['TEXT', 'IMAGE'] : ['IMAGE']];

        $imageConfig = array_filter([
            'aspectRatio' => $this->aspectRatio,
            'imageSize' => $this->size,
        ]);

        if ($imageConfig !== []) {
            $config['imageConfig'] = $imageConfig;
        }

        return $config;
    }
}
