<?php

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Testing\FakeResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

it('generates a video and stores it', function () {
    Storage::fake('local');
    Gemini::fake([FakeResponse::video(id: 'interaction-1')]);

    $response = Gemini::video('A lighthouse at dusk')->generate();
    $path = $response->store('videos', 'local');

    expect($response->id)->toBe('interaction-1')
        ->and($path)->toStartWith('videos/')->toEndWith('.mp4')
        ->and(Storage::disk('local')->get($path))->toBe(FakeResponse::VIDEO);
});

it('sends the interaction payload', function () {
    Gemini::fake();

    Gemini::video('A lighthouse at dusk')->model('my-video-model')->aspectRatio('9:16')->resolution('1080p')->generate();

    Gemini::assertSent(fn (array $payload, string $model) => $model === 'my-video-model'
        && $payload === [
            'model' => 'my-video-model',
            'input' => 'A lighthouse at dusk',
            'response_format' => ['type' => 'video', 'aspect_ratio' => '9:16', 'resolution' => '1080p', 'delivery' => 'uri'],
        ]);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
        && $request->hasHeader('x-goog-api-key', 'test-key'));
});

it('animates an image', function () {
    Gemini::fake();

    Gemini::video('Make the waves move')->withImageData(base64_decode(FakeResponse::PNG), 'image/png')->inline()->generate();

    Gemini::assertSent(fn (array $payload) => $payload['input'] === [
        ['type' => 'image', 'data' => FakeResponse::PNG, 'mime_type' => 'image/png'],
        ['type' => 'text', 'text' => 'Make the waves move'],
    ] && ! isset($payload['response_format']['delivery']));
});

it('extends an earlier video', function () {
    Gemini::fake();

    Gemini::video('Now the camera pans up')->after('interaction-1')->generate();

    Gemini::assertSent(fn (array $payload) => $payload['previous_interaction_id'] === 'interaction-1');
});

it('waits for the file and downloads it', function () {
    Sleep::fake();
    Storage::fake('local');
    Http::fake([
        '*/interactions' => Http::response(FakeResponse::video(uri: 'https://generativelanguage.googleapis.com/v1beta/files/abc123')),
        '*/files/abc123:download*' => Http::response('real-video-bytes'),
        '*/files/abc123' => Http::sequence()
            ->push(['name' => 'files/abc123', 'state' => 'PROCESSING'])
            ->push(['name' => 'files/abc123', 'state' => 'ACTIVE']),
    ]);

    $path = Gemini::video('A lighthouse')->generate()->store('videos', 'local');

    expect(Storage::disk('local')->get($path))->toBe('real-video-bytes');
    Sleep::assertSleptTimes(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'files/abc123:download?alt=media'));
});

it('throws when the file fails to process', function () {
    Http::fake([
        '*/interactions' => Http::response(FakeResponse::video(uri: 'https://generativelanguage.googleapis.com/v1beta/files/abc123')),
        '*/files/abc123' => Http::response(['name' => 'files/abc123', 'state' => 'FAILED']),
    ]);

    Gemini::video('A lighthouse')->generate()->video()->bytes();
})->throws(GeminiException::class, 'state: FAILED');

it('throws when no video is returned', function () {
    Gemini::fake([[
        'id' => 'interaction-1',
        'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'I cannot make that video.']]]],
    ]]);

    Gemini::video('Something unsafe')->generate();
})->throws(GeminiException::class, 'I cannot make that video.');

it('generates a video from the command line', function () {
    Storage::fake('local');
    Gemini::fake();

    $this->artisan('gemini:video', ['prompt' => 'A lighthouse', '--disk' => 'local', '--resolution' => '720p'])
        ->assertSuccessful();

    expect(Storage::disk('local')->files('gemini'))->toHaveCount(1);
});
