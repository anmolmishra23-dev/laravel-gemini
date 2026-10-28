<?php

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Testing\FakeResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('generates an image and stores it', function () {
    Storage::fake('local');
    Gemini::fake([FakeResponse::image()]);

    $path = Gemini::image('A cozy cabin')->generate()->store('images', 'local');

    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith('images/')->toEndWith('.png')
        ->and(Storage::disk('local')->get($path))->toBe(base64_decode(FakeResponse::PNG));
});

it('saves an image to an exact path', function () {
    Storage::fake('local');
    Gemini::fake();

    Gemini::image('A cozy cabin')->generate()->save('cabin.png', 'local');

    Storage::disk('local')->assertExists('cabin.png');
});

it('sends the model, prompt and image config', function () {
    Gemini::fake();

    Gemini::image('A cozy cabin')->model('my-image-model')->aspectRatio('16:9')->size('2K')->generate();

    Gemini::assertSent(fn (array $payload, string $model) => $model === 'my-image-model'
        && $payload['contents'][0]['parts'] === [['text' => 'A cozy cabin']]
        && $payload['generationConfig'] === [
            'responseModalities' => ['IMAGE'],
            'imageConfig' => ['aspectRatio' => '16:9', 'imageSize' => '2K'],
        ]);
});

it('uses the configured default model', function () {
    config(['gemini.models.image' => 'configured-model']);
    Gemini::fake();

    Gemini::image('A cozy cabin')->generate();

    Gemini::assertSent(fn (array $payload, string $model) => $model === 'configured-model');
});

it('authenticates with the api key header', function () {
    Gemini::fake();

    Gemini::image('A cozy cabin')->generate();

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key'));
});

it('sends input images before the prompt for editing', function () {
    Storage::fake('local');
    Storage::disk('local')->put('input.png', base64_decode(FakeResponse::PNG));
    Gemini::fake();

    Gemini::image('Make it blue')->withImageFromDisk('input.png', 'local')->generate();

    Gemini::assertSent(function (array $payload) {
        $parts = $payload['contents'][0]['parts'];

        return $parts[0]['inlineData'] === ['mimeType' => 'image/png', 'data' => FakeResponse::PNG]
            && $parts[1] === ['text' => 'Make it blue'];
    });
});

it('detects the mime type of raw image bytes', function () {
    Gemini::fake();

    Gemini::image('Make it blue')->withImageData(base64_decode(FakeResponse::PNG))->generate();

    Gemini::assertSent(fn (array $payload) => $payload['contents'][0]['parts'][0]['inlineData']['mimeType'] === 'image/png');
});

it('returns text alongside the image when requested', function () {
    Gemini::fake([FakeResponse::image(text: 'Here is your cabin.')]);

    $response = Gemini::image('A cozy cabin')->withText()->generate();

    expect($response->text)->toBe('Here is your cabin.')
        ->and($response->images())->toHaveCount(1)
        ->and($response->usage->totalTokens)->toBe(30);

    Gemini::assertSent(fn (array $payload) => $payload['generationConfig']['responseModalities'] === ['TEXT', 'IMAGE']);
});

it('throws when no image is returned', function () {
    Gemini::fake([FakeResponse::text('I cannot draw that.', 'SAFETY')]);

    Gemini::image('Something unsafe')->generate();
})->throws(GeminiException::class, 'finish reason: SAFETY');

it('throws on api errors', function () {
    Gemini::fake([FakeResponse::error(429, 'Quota exceeded')]);

    try {
        Gemini::image('A cozy cabin')->generate();
        $this->fail('Expected a GeminiException.');
    } catch (GeminiException $e) {
        expect($e->status)->toBe(429)
            ->and($e->isRateLimited())->toBeTrue()
            ->and($e->getMessage())->toContain('Quota exceeded');
    }
});

it('throws when the prompt is blocked', function () {
    Gemini::fake([['promptFeedback' => ['blockReason' => 'SAFETY']]]);

    Gemini::image('Something unsafe')->generate();
})->throws(GeminiException::class, 'blocked the prompt');

it('throws without an api key', function () {
    config(['gemini.api_key' => null]);
    Http::fake();

    Gemini::image('A cozy cabin')->generate();
})->throws(GeminiException::class, 'API key is missing');

it('can be returned as an http response', function () {
    Gemini::fake();

    $response = Gemini::image('A cozy cabin')->generate()->image()->toResponse(new HttpRequest);

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->getContent())->toBe(base64_decode(FakeResponse::PNG));
});

it('generates an image from the command line', function () {
    Storage::fake('local');
    Gemini::fake();

    $this->artisan('gemini:image', ['prompt' => 'A cozy cabin', '--disk' => 'local', '--aspect-ratio' => '1:1'])
        ->assertSuccessful();

    expect(Storage::disk('local')->files('gemini'))->toHaveCount(1);
    Gemini::assertSent(fn (array $payload) => $payload['generationConfig']['imageConfig'] === ['aspectRatio' => '1:1']);
});
