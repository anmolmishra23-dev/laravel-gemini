<?php

use AnmolMishra\Gemini\Events\GenerationFailed;
use AnmolMishra\Gemini\Events\ImageGenerated;
use AnmolMishra\Gemini\Events\VideoGenerated;
use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Jobs\GenerateMedia;
use AnmolMishra\Gemini\Requests\ImageRequest;
use AnmolMishra\Gemini\Testing\FakeResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

it('dispatches a job with the request and meta', function () {
    Bus::fake();

    Gemini::image('A cozy cabin')->queue('products', 's3', ['product_id' => 5]);

    Bus::assertDispatched(GenerateMedia::class, fn (GenerateMedia $job) => $job->request instanceof ImageRequest
        && $job->directory === 'products'
        && $job->disk === 's3'
        && $job->meta === ['product_id' => 5]);
});

it('uses the configured queue connection and name', function () {
    config(['gemini.queue.connection' => 'redis', 'gemini.queue.name' => 'ai']);
    Bus::fake();

    Gemini::image('A cozy cabin')->queue();

    Bus::assertDispatched(GenerateMedia::class, fn (GenerateMedia $job) => $job->connection === 'redis' && $job->queue === 'ai');
});

it('does not put the api key in the queued payload', function () {
    config(['gemini.api_key' => 'super-secret-key']);

    $payload = serialize(new GenerateMedia(Gemini::image('A cozy cabin')->withImageFromDisk('input.png', 'local')));

    expect($payload)->not->toContain('super-secret-key');
});

it('generates after being unserialized', function () {
    Gemini::fake();

    $request = unserialize(serialize(Gemini::image('A cozy cabin')->aspectRatio('16:9')));
    $request->generate();

    Gemini::assertSent(fn (array $payload) => $payload['generationConfig']['imageConfig'] === ['aspectRatio' => '16:9']);
});

it('stores the image and fires ImageGenerated', function () {
    Storage::fake('local');
    Event::fake([ImageGenerated::class]);
    Gemini::fake([FakeResponse::image(text: 'Done')]);

    Gemini::image('A cozy cabin')->queue('products', 'local', ['product_id' => 5]);

    Event::assertDispatched(ImageGenerated::class, function (ImageGenerated $event) {
        Storage::disk('local')->assertExists($event->path);

        return str_starts_with($event->path, 'products/')
            && $event->meta === ['product_id' => 5]
            && $event->text === 'Done';
    });
});

it('stores the video and fires VideoGenerated', function () {
    Storage::fake('local');
    Event::fake([VideoGenerated::class]);
    Gemini::fake([FakeResponse::video(id: 'interaction-1')]);

    Gemini::video('A lighthouse at dusk')->queue('videos', 'local');

    Event::assertDispatched(VideoGenerated::class, fn (VideoGenerated $event) => str_ends_with($event->path, '.mp4')
        && $event->interactionId === 'interaction-1'
        && Storage::disk('local')->get($event->path) === FakeResponse::VIDEO);
});

it('fires GenerationFailed when generation fails', function () {
    Event::fake([GenerationFailed::class]);
    Gemini::fake([FakeResponse::error(400, 'Bad prompt')]);

    try {
        Gemini::image('A cozy cabin')->queue(meta: ['product_id' => 5]);
    } catch (Throwable) {
        // The sync queue rethrows after calling failed().
    }

    Event::assertDispatched(GenerationFailed::class, fn (GenerationFailed $event) => $event->type === 'image'
        && $event->meta === ['product_id' => 5]
        && str_contains($event->exception->getMessage(), 'Bad prompt'));
});
