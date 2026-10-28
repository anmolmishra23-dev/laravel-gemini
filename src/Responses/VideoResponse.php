<?php

namespace AnmolMishra\Gemini\Responses;

use AnmolMishra\Gemini\Exceptions\GeminiException;

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

    public function save(string $path, ?string $disk = null): string
    {
        return $this->video()->save($path, $disk);
    }

    public function store(string $directory = '', ?string $disk = null): string
    {
        return $this->video()->store($directory, $disk);
    }
}
