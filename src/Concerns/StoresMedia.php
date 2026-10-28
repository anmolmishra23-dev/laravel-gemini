<?php

namespace AnmolMishra\Gemini\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

trait StoresMedia
{
    abstract public function bytes(): string;

    abstract public function extension(): string;

    public function base64(): string
    {
        return base64_encode($this->bytes());
    }

    /**
     * A data URI for use directly in an <img> or <video> src.
     */
    public function dataUri(): string
    {
        return "data:{$this->mimeType};base64,{$this->base64()}";
    }

    /**
     * Save to an exact path on a storage disk and return the path.
     */
    public function save(string $path, ?string $disk = null): string
    {
        if (! Storage::disk($disk ?? config('gemini.disk'))->put($path, $this->bytes())) {
            throw new RuntimeException("Could not save the generated file to [{$path}].");
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
     * Return the file straight from a route or controller.
     */
    public function toResponse($request): Response
    {
        return response($this->bytes(), 200, ['Content-Type' => $this->mimeType]);
    }
}
