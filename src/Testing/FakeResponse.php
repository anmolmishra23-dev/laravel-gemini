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

    public static function error(int $status = 500, string $message = 'Internal error'): PromiseInterface
    {
        return Http::response(['error' => ['code' => $status, 'message' => $message]], $status);
    }

    /**
     * Build a generateContent response from raw parts.
     */
    public static function make(array $parts, string $finishReason = 'STOP'): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => $parts],
                'finishReason' => $finishReason,
            ]],
            'usageMetadata' => [
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
