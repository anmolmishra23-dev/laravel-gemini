<?php

namespace AnmolMishra\Gemini\Responses;

use AnmolMishra\Gemini\Concerns\StoresMedia;
use Illuminate\Contracts\Support\Responsable;

class GeneratedImage implements Responsable
{
    use StoresMedia;

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

    public function extension(): string
    {
        return match ($this->mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
    }
}
