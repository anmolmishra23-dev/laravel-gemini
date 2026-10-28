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
     * Attached images. Disk and URL images are only read when the request is
     * sent, so queued requests stay small.
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
        $this->images[] = ['source' => 'disk', 'path' => $path, 'disk' => $disk];

        return $this;
    }

    /**
     * Attach an image from a URL.
     */
    public function withImageUrl(string $url): static
    {
        $this->images[] = ['source' => 'url', 'url' => $url];

        return $this;
    }

    /**
     * Attach raw image bytes.
     */
    public function withImageData(string $bytes, ?string $mimeType = null): static
    {
        $this->images[] = [
            'source' => 'data',
            'data' => base64_encode($bytes),
            'mimeType' => $mimeType ?? $this->detectMimeType($bytes),
        ];

        return $this;
    }

    /**
     * Load every attached image.
     *
     * @return array<int, array{data: string, mimeType: string}>
     */
    protected function resolveImages(): array
    {
        return array_map(fn (array $image) => match ($image['source']) {
            'disk' => $this->loadFromDisk($image['path'], $image['disk']),
            'url' => $this->loadFromUrl($image['url']),
            default => $image,
        }, $this->images);
    }

    protected function loadFromDisk(string $path, ?string $disk): array
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new InvalidArgumentException("Image [{$path}] does not exist on the disk.");
        }

        $bytes = $storage->get($path);

        return ['data' => base64_encode($bytes), 'mimeType' => $storage->mimeType($path) ?: $this->detectMimeType($bytes)];
    }

    protected function loadFromUrl(string $url): array
    {
        $response = Http::timeout(30)->get($url)->throw();
        $mimeType = Str::before($response->header('Content-Type'), ';');

        return ['data' => base64_encode($response->body()), 'mimeType' => $mimeType ?: $this->detectMimeType($response->body())];
    }

    protected function detectMimeType(string $bytes): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
    }
}
