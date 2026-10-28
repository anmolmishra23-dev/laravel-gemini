<?php

namespace AnmolMishra\Gemini\Responses;

use AnmolMishra\Gemini\Exceptions\GeminiException;

class ImageResponse extends GeminiResponse
{
    /**
     * @return GeneratedImage[]
     */
    public function images(): array
    {
        return $this->images;
    }

    /**
     * The first generated image.
     */
    public function image(): GeneratedImage
    {
        return $this->images[0] ?? throw GeminiException::noImageReturned($this->finishReason, $this->text);
    }

    /**
     * Save the first image to an exact path.
     */
    public function save(string $path, ?string $disk = null): string
    {
        return $this->image()->save($path, $disk);
    }

    /**
     * Save the first image under a random name in the given directory.
     */
    public function store(string $directory = '', ?string $disk = null): string
    {
        return $this->image()->store($directory, $disk);
    }
}
