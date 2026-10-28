<?php

namespace AnmolMishra\Gemini\Concerns;

use finfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SplFileInfo;

trait HasImageInputs
{
    /**
     * Image parts sent before the prompt text.
     */
    protected array $images = [];

    /**
     * Attach an image from a local path or an uploaded file.
     */
    public function withImage(string|SplFileInfo $image, ?string $mimeType = null): static
    {
        $path = $image instanceof SplFileInfo ? $image->getRealPath() : $image;

        if (! is_string($path) || ! is_file($path)) {
            throw new InvalidArgumentException("Image file [{$path}] does not exist.");
        }

        return $this->withImageData(file_get_contents($path), $mimeType);
    }

    /**
     * Attach an image from a Laravel storage disk.
     */
    public function withImageFromDisk(string $path, ?string $disk = null): static
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new InvalidArgumentException("Image [{$path}] does not exist on the disk.");
        }

        return $this->withImageData($storage->get($path), $storage->mimeType($path) ?: null);
    }

    /**
     * Download an image from a URL and attach it.
     */
    public function withImageUrl(string $url): static
    {
        $response = Http::timeout(30)->get($url)->throw();

        $mimeType = Str::before($response->header('Content-Type'), ';') ?: null;

        return $this->withImageData($response->body(), $mimeType);
    }

    /**
     * Attach raw image bytes.
     */
    public function withImageData(string $bytes, ?string $mimeType = null): static
    {
        $this->images[] = [
            'inlineData' => [
                'mimeType' => $mimeType ?? $this->detectMimeType($bytes),
                'data' => base64_encode($bytes),
            ],
        ];

        return $this;
    }

    protected function detectMimeType(string $bytes): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
    }
}
