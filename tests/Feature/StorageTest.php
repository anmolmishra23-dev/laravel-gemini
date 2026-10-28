<?php

use AnmolMishra\Gemini\Events\ImageGenerated;
use AnmolMishra\Gemini\Events\VideoGenerated;
use AnmolMishra\Gemini\Facades\Gemini;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

it('saves to any configured disk', function (string $disk) {
    Storage::fake($disk);
    Gemini::fake();

    $path = Gemini::image('A cozy cabin')->generate()->store('images', $disk);

    Storage::disk($disk)->assertExists($path);
})->with(['s3', 'gcs', 'r2']);

it('uses the configured default disk', function () {
    config(['gemini.disk' => 'gcs']);
    Storage::fake('gcs');
    Gemini::fake();

    $image = Gemini::image('A cozy cabin')->generate()->image();
    $path = $image->store('images');

    Storage::disk('gcs')->assertExists($path);
    expect($image->disk())->toBe('gcs')->and($image->path())->toBe($path);
});

it('gives the url of a stored image', function () {
    Storage::fake('s3');
    Gemini::fake();

    $response = Gemini::image('A cozy cabin')->generate();
    $path = $response->store('images', 's3');

    expect($response->url())->toBe(Storage::disk('s3')->url($path))
        ->and($response->image()->url())->toEndWith($path);
});

it('gives an expiring url for private buckets', function () {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path, $expiration) => "https://signed.example.com/{$path}?expires={$expiration->getTimestamp()}");
    Gemini::fake();

    $expires = now()->addHour();
    $response = Gemini::image('A cozy cabin')->generate();
    $path = $response->store('images', 's3');

    expect($response->temporaryUrl($expires))->toBe("https://signed.example.com/{$path}?expires={$expires->getTimestamp()}");
});

it('refuses to give a url before the image is saved', function () {
    Gemini::fake();

    Gemini::image('A cozy cabin')->generate()->url();
})->throws(RuntimeException::class, 'Save the file with store() or save()');

it('stores files publicly', function () {
    Storage::fake('s3');
    Gemini::fake();

    $path = Gemini::image('A cozy cabin')->generate()->storePublicly('images', 's3');

    expect(Storage::disk('s3')->getVisibility($path))->toBe('public');
});

it('uses the visibility passed to store or set in the config', function () {
    Storage::fake('s3');
    Gemini::fake();

    $private = Gemini::image('A cozy cabin')->generate()->store('images', 's3', 'private');

    config(['gemini.visibility' => 'public']);
    $public = Gemini::image('A cozy cabin')->generate()->store('images', 's3');

    expect(Storage::disk('s3')->getVisibility($private))->toBe('private')
        ->and(Storage::disk('s3')->getVisibility($public))->toBe('public');
});

it('stores videos on any disk with a url', function () {
    Storage::fake('s3');
    Gemini::fake();

    $response = Gemini::video('A lighthouse at dusk')->generate();
    $path = $response->storePublicly('videos', 's3');

    expect(Storage::disk('s3')->getVisibility($path))->toBe('public')
        ->and($response->url())->toEndWith($path);
});

it('stores a batch publicly and returns the urls', function () {
    Storage::fake('s3');
    Gemini::fake();

    $results = Gemini::batch(['mug' => 'A mug', 'shirt' => 'A shirt'])->generate();
    $paths = $results->storePublicly('catalog', 's3');

    expect(array_keys($results->urls()))->toBe(['mug', 'shirt'])
        ->and($results->urls()['mug'])->toEndWith($paths['mug'])
        ->and(Storage::disk('s3')->getVisibility($paths['shirt']))->toBe('public');
});

it('gives the url in queued events', function () {
    config(['gemini.disk' => 's3']);
    Storage::fake('s3');
    Event::fake([ImageGenerated::class, VideoGenerated::class]);
    Gemini::fake();

    Gemini::image('A cozy cabin')->queue('products', visibility: 'public');
    Gemini::video('A lighthouse')->queue('videos');

    Event::assertDispatched(ImageGenerated::class, fn (ImageGenerated $event) => $event->disk === 's3'
        && $event->url() === Storage::disk('s3')->url($event->path)
        && Storage::disk('s3')->getVisibility($event->path) === 'public');
    Event::assertDispatched(VideoGenerated::class, fn (VideoGenerated $event) => $event->disk === 's3'
        && str_ends_with($event->url(), $event->path));
});

it('saves publicly from the command line and prints the url', function () {
    Storage::fake('s3');
    Gemini::fake();

    $this->artisan('gemini:image', ['prompt' => 'A cozy cabin', '--disk' => 's3', '--public' => true])
        ->expectsOutputToContain('URL:')
        ->assertSuccessful();

    $path = Storage::disk('s3')->files('gemini')[0];
    expect(Storage::disk('s3')->getVisibility($path))->toBe('public');
});
