<?php

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Responses\ImageResponse;
use AnmolMishra\Gemini\Responses\TextResponse;
use AnmolMishra\Gemini\Testing\FakeResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Answer each request based on its prompt, since batch requests run concurrently.
 */
function fakeByPrompt(array $responses): void
{
    Http::fake(function (Request $request) use ($responses) {
        $prompt = collect($request->data()['contents'][0]['parts'])->pluck('text')->filter()->first();

        return is_array($responses[$prompt]) ? Http::response($responses[$prompt]) : $responses[$prompt];
    });
}

it('generates many images keyed like the requests', function () {
    Gemini::fake();

    $results = Gemini::batch([
        'mug' => 'A white coffee mug',
        'shirt' => Gemini::image('A black t-shirt')->aspectRatio('1:1'),
    ])->generate();

    expect($results)->toHaveCount(2)
        ->and($results->get('mug'))->toBeInstanceOf(ImageResponse::class)
        ->and($results->get('shirt'))->toBeInstanceOf(ImageResponse::class)
        ->and($results->hasFailures())->toBeFalse();

    Gemini::assertSentCount(2);
});

it('mixes image and text requests', function () {
    Gemini::fake();

    $results = Gemini::batch([
        'image' => Gemini::image('A mug'),
        'caption' => Gemini::text('Write a caption for a mug'),
    ])->generate();

    expect($results->get('image'))->toBeInstanceOf(ImageResponse::class)
        ->and($results->get('caption'))->toBeInstanceOf(TextResponse::class);
});

it('keeps going when some requests fail', function () {
    config(['gemini.api_key' => 'test-key', 'gemini.retry.times' => 1]);
    fakeByPrompt([
        'A mug' => FakeResponse::image(),
        'A shirt' => FakeResponse::error(429, 'Quota exceeded'),
        'A hat' => FakeResponse::text('I cannot draw that.', 'SAFETY'),
    ]);

    $results = Gemini::batch(['mug' => 'A mug', 'shirt' => 'A shirt', 'hat' => 'A hat'])->generate();

    expect(array_keys($results->successful()))->toBe(['mug'])
        ->and(array_keys($results->failed()))->toBe(['shirt', 'hat'])
        ->and($results->get('shirt'))->toBeInstanceOf(GeminiException::class)
        ->and($results->get('shirt')->isRateLimited())->toBeTrue()
        ->and($results->get('hat')->getMessage())->toContain('SAFETY');
});

it('runs in chunks of the given concurrency', function () {
    Gemini::fake();

    $results = Gemini::batch(array_map(fn ($i) => "Product {$i}", range(1, 7)))->concurrency(3)->generate();

    expect($results)->toHaveCount(7)->and($results->hasFailures())->toBeFalse();
    Gemini::assertSentCount(7);
});

it('stores every generated image', function () {
    Storage::fake('local');
    Gemini::fake();

    $paths = Gemini::batch(['mug' => 'A mug', 'shirt' => 'A shirt'])->generate()->store('catalog', 'local');

    expect(array_keys($paths))->toBe(['mug', 'shirt']);
    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
});

it('throws for a missing api key', function () {
    config(['gemini.api_key' => null]);
    Http::fake();

    Gemini::batch(['A mug'])->generate();
})->throws(GeminiException::class, 'API key is missing');
