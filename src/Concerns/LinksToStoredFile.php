<?php

namespace AnmolMishra\Gemini\Concerns;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;

/**
 * URL helpers for events that carry a stored file's $path and $disk.
 */
trait LinksToStoredFile
{
    /**
     * The public URL of the stored file.
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * A link to the stored file that expires, for private buckets.
     */
    public function temporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, $expiration, $options);
    }
}
