<?php

namespace AnmolMishra\Gemini\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

class FakeResponse
{
    /**
     * A 1x1 PNG, base64 encoded.
     */
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Bytes returned for fake videos.
     */
    public const VIDEO = 'fake-mp4-video';

    public static function text(string $text, string $finishReason = 'STOP'): array
    {
        return static::make([['text' => $text]], $finishReason);
    }

    public static function image(?string $bytes = null, string $mimeType = 'image/png', ?string $text = null): array
    {
        $parts = $text === null ? [] : [['text' => $text]];

        $parts[] = ['inlineData' => [
            'mimeType' => $mimeType,
            'data' => $bytes === null ? self::PNG : base64_encode($bytes),
        ]];

        return static::make($parts);
    }

    /**
     * A video interaction response. Pass a $uri to fake a file that must be downloaded.
     */
    public static function video(?string $bytes = null, ?string $uri = null, string $id = 'fake-interaction'): array
    {
        $video = ['type' => 'video', 'mime_type' => 'video/mp4'];

        if ($uri !== null) {
            $video['uri'] = $uri;
        } else {
            $video['data'] = base64_encode($bytes ?? self::VIDEO);
        }

        return [
            'id' => $id,
            'steps' => [['type' => 'model_output', 'content' => [$video]]],
        ];
    }

    public static function error(int $status = 500, string $message = 'Internal error'): PromiseInterface
    {
        return Http::response(['error' => ['code' => $status, 'message' => $message]], $status);
    }

    /**
     * Build a generateContent response from raw parts.
     */
    public static function make(array $parts, string $finishReason = 'STOP', ?array $usage = null): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => $parts],
                'finishReason' => $finishReason,
            ]],
            'usageMetadata' => $usage ?? [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 20,
                'totalTokenCount' => 30,
            ],
        ];
    }

    public static function default(): array
    {
        return static::make([
            ['text' => 'This is a fake Gemini response.'],
            ['inlineData' => ['mimeType' => 'image/png', 'data' => self::PNG]],
        ]);
    }
}
