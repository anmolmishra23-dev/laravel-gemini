<?php

namespace AnmolMishra\Gemini\Concerns;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saving generated files to any Laravel storage disk: local, S3, Google Cloud
 * Storage, Cloudflare R2, DigitalOcean Spaces, Azure, FTP and so on. Whatever
 * disk the app has configured in config/filesystems.php works here.
 */
trait StoresMedia
{
    /**
     * Where the file was last saved, so url() knows what to link to.
     */
    protected ?string $storedPath = null;

    protected ?string $storedDisk = null;

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
     *
     * @param  string|null  $disk  any disk from config/filesystems.php, e.g. "s3" or "gcs"
     * @param  string|null  $visibility  "public", "private", or null for the disk's default
     */
    public function save(string $path, ?string $disk = null, ?string $visibility = null): string
    {
        $disk ??= config('gemini.disk');
        $visibility ??= config('gemini.visibility');
        $options = $visibility === null ? [] : ['visibility' => $visibility];

        if (! Storage::disk($disk)->put($path, $this->bytes(), $options)) {
            throw new RuntimeException("Could not save the generated file to [{$path}].");
        }

        $this->storedPath = $path;
        $this->storedDisk = $disk;

        return $path;
    }

    /**
     * Save under a random file name in a directory and return the path.
     */
    public function store(string $directory = '', ?string $disk = null, ?string $visibility = null): string
    {
        $path = ltrim(trim($directory, '/').'/'.Str::random(40).'.'.$this->extension(), '/');

        return $this->save($path, $disk, $visibility);
    }

    /**
     * Save under a random file name, readable by anyone with the URL.
     */
    public function storePublicly(string $directory = '', ?string $disk = null): string
    {
        return $this->store($directory, $disk, 'public');
    }

    /**
     * The path the file was saved to, or null if it hasn't been saved yet.
     */
    public function path(): ?string
    {
        return $this->storedPath;
    }

    /**
     * The disk the file was saved to (null means the default disk).
     */
    public function disk(): ?string
    {
        return $this->storedDisk;
    }

    /**
     * The public URL of the saved file, e.g. https://my-bucket.s3.amazonaws.com/images/abc.png
     */
    public function url(): string
    {
        return Storage::disk($this->storedDisk)->url($this->storedPathOrFail());
    }

    /**
     * A link to the saved file that expires, for private buckets.
     */
    public function temporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        return Storage::disk($this->storedDisk)->temporaryUrl($this->storedPathOrFail(), $expiration, $options);
    }

    /**
     * Return the file straight from a route or controller.
     */
    public function toResponse($request): Response
    {
        return response($this->bytes(), 200, ['Content-Type' => $this->mimeType]);
    }

    protected function storedPathOrFail(): string
    {
        return $this->storedPath ?? throw new RuntimeException('Save the file with store() or save() before asking for its URL.');
    }
}
