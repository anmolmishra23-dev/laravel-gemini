<?php

namespace AnmolMishra\Gemini\Responses;

use AnmolMishra\Gemini\Concerns\StoresMedia;
use AnmolMishra\Gemini\GeminiClient;
use Illuminate\Contracts\Support\Responsable;

class GeneratedVideo implements Responsable
{
    use StoresMedia;

    protected ?string $downloaded = null;

    /**
     * A video is either inline (base64) or a Gemini file URI that is downloaded on first use.
     */
    public function __construct(
        public readonly ?string $data = null,
        public readonly ?string $uri = null,
        public readonly string $mimeType = 'video/mp4',
    ) {}

    public function bytes(): string
    {
        if ($this->data !== null) {
            return base64_decode($this->data);
        }

        return $this->downloaded ??= app(GeminiClient::class)->downloadFile($this->uri);
    }

    public function extension(): string
    {
        return match ($this->mimeType) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };
    }
}
