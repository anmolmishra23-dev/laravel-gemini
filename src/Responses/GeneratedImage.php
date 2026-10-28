<?php

namespace AnmolMishra\Gemini\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GeneratedImage implements Responsable
{
    public function __construct(
        public readonly string $base64,
        public readonly string $mimeType = 'image/png',
    ) {}

    public function bytes(): string
    {
        return base64_decode($this->base64);
    }

    public function base64(): string
    {
        return $this->base64;
    }

    /**
     * A data URI for use directly in an <img src="...">.
     */
    public function dataUri(): string
    {
        return "data:{$this->mimeType};base64,{$this->base64}";
    }

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
    }

    /**
     * Save to an exact path on a storage disk and return the path.
     */
    public function save(string $path, ?string $disk = null): string
    {
        if (! Storage::disk($disk ?? config('gemini.disk'))->put($path, $this->bytes())) {
            throw new RuntimeException("Could not save the generated image to [{$path}].");
        }

        return $path;
    }

    /**
     * Save under a random file name in a directory and return the path.
     */
    public function store(string $directory = '', ?string $disk = null): string
    {
        $path = ltrim(trim($directory, '/').'/'.Str::random(40).'.'.$this->extension(), '/');

        return $this->save($path, $disk);
    }

    /**
     * Return the image straight from a route or controller.
     */
    public function toResponse($request): Response
    {
        return response($this->bytes(), 200, ['Content-Type' => $this->mimeType]);
    }
}
