<?php

namespace AnmolMishra\Gemini\Responses;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use DateTimeInterface;

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
    public function save(string $path, ?string $disk = null, ?string $visibility = null): string
    {
        return $this->image()->save($path, $disk, $visibility);
    }

    /**
     * Save the first image under a random name in the given directory.
     */
    public function store(string $directory = '', ?string $disk = null, ?string $visibility = null): string
    {
        return $this->image()->store($directory, $disk, $visibility);
    }

    /**
     * Save the first image so anyone with the URL can view it.
     */
    public function storePublicly(string $directory = '', ?string $disk = null): string
    {
        return $this->image()->storePublicly($directory, $disk);
    }

    /**
     * The URL of the first image, after saving it.
     */
    public function url(): string
    {
        return $this->image()->url();
    }

    /**
     * An expiring link to the first image, after saving it.
     */
    public function temporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        return $this->image()->temporaryUrl($expiration, $options);
    }
}
