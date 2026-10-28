<?php

namespace AnmolMishra\Gemini\Responses;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use DateTimeInterface;

class VideoResponse
{
    /**
     * @param  GeneratedVideo[]  $videos
     */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $text,
        public readonly array $raw,
        public readonly ?string $model = null,
        protected array $videos = [],
    ) {}

    public static function fromArray(array $data, ?string $model = null): self
    {
        $text = null;
        $videos = [];

        foreach ($data['steps'] ?? [] as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ($step['content'] ?? [] as $content) {
                match ($content['type'] ?? null) {
                    'video' => $videos[] = new GeneratedVideo(
                        $content['data'] ?? null,
                        $content['uri'] ?? null,
                        $content['mime_type'] ?? 'video/mp4',
                    ),
                    'text' => $text .= $content['text'] ?? '',
                    default => null,
                };
            }
        }

        return new self(
            id: $data['id'] ?? null,
            text: $text === null ? null : trim($text),
            raw: $data,
            model: $model ?? $data['model'] ?? null,
            videos: array_values(array_filter($videos, fn (GeneratedVideo $video) => $video->data !== null || $video->uri !== null)),
        );
    }

    /**
     * @return GeneratedVideo[]
     */
    public function videos(): array
    {
        return $this->videos;
    }

    public function video(): GeneratedVideo
    {
        return $this->videos[0] ?? throw GeminiException::noVideoReturned($this->text);
    }

    /**
     * Save the first video to an exact path.
     */
    public function save(string $path, ?string $disk = null, ?string $visibility = null): string
    {
        return $this->video()->save($path, $disk, $visibility);
    }

    /**
     * Save the first video under a random name in the given directory.
     */
    public function store(string $directory = '', ?string $disk = null, ?string $visibility = null): string
    {
        return $this->video()->store($directory, $disk, $visibility);
    }

    /**
     * Save the first video so anyone with the URL can watch it.
     */
    public function storePublicly(string $directory = '', ?string $disk = null): string
    {
        return $this->video()->storePublicly($directory, $disk);
    }

    /**
     * The URL of the first video, after saving it.
     */
    public function url(): string
    {
        return $this->video()->url();
    }

    /**
     * An expiring link to the first video, after saving it.
     */
    public function temporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        return $this->video()->temporaryUrl($expiration, $options);
    }
}
