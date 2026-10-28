# Laravel Gemini

[![Tests](https://github.com/anmolmishra23-dev/laravel-gemini/actions/workflows/tests.yml/badge.svg)](https://github.com/anmolmishra23-dev/laravel-gemini/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/anmolmishra23-dev/laravel-gemini.svg)](https://packagist.org/packages/anmolmishra23-dev/laravel-gemini)
[![Total Downloads](https://img.shields.io/packagist/dt/anmolmishra23-dev/laravel-gemini.svg)](https://packagist.org/packages/anmolmishra23-dev/laravel-gemini)
[![License](https://img.shields.io/packagist/l/anmolmishra23-dev/laravel-gemini.svg)](LICENSE)

An unofficial Laravel SDK for Google Gemini, built for **image and video generation** first, with text generation included.

```php
use AnmolMishra\Gemini\Facades\Gemini;

Gemini::image('A cozy cabin in snowy mountains at sunset')
    ->aspectRatio('16:9')
    ->generate()
    ->store('images'); // images/Xy3...png
```

- **Images:** generate, edit with reference images, and refine over several turns
- **Batches:** generate a whole product catalog concurrently, or on the queue
- **Queues:** generate in the background, with events when the image or video is ready
- **Video:** text-to-video and image-to-video with Gemini Omni
- **Text:** system instructions, vision and structured JSON output
- **Cost tracking:** an estimated USD cost for every response
- Save straight to any Laravel storage disk, or return files from routes
- Automatic retries on 429 / 5xx, with clear exceptions
- `Gemini::fake()` and assertions for testing your app

> This package is not affiliated with or endorsed by Google.

## Contents

- [Installation](#installation)
- [Image generation](#image-generation)
- [Image editing](#image-editing-and-reference-images)
- [Multi-turn editing](#multi-turn-editing)
- [Batch generation](#batch-generation)
- [Queues and events](#queues-and-events)
- [Video generation](#video-generation)
- [Text generation](#text-generation)
- [Cost tracking](#cost-tracking)
- [Error handling](#error-handling)
- [Artisan commands](#artisan-commands)
- [Testing your app](#testing-your-app)
- [Configuration](#configuration)

## Requirements

- PHP 8.2+
- Laravel 12 or 13

## Installation

```bash
composer require anmolmishra23-dev/laravel-gemini
```

Add your API key (get one at [Google AI Studio](https://aistudio.google.com/apikey)) to `.env`:

```env
GEMINI_API_KEY=your-api-key
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=gemini-config
```

## Image generation

```php
use AnmolMishra\Gemini\Facades\Gemini;

$response = Gemini::image('A cozy cabin in snowy mountains at sunset')
    ->model('gemini-3-pro-image')   // optional, defaults to config('gemini.models.image')
    ->aspectRatio('16:9')           // 1:1, 16:9, 9:16, 4:3, 3:4, ...
    ->size('2K')                    // 1K, 2K, 4K (supported models only)
    ->generate();
```

### Working with the result

```php
$image = $response->image();          // first image

$image->store('images');              // random name on the default disk, returns the path
$image->store('images', 's3');        // on a specific disk
$image->save('images/cabin.png');     // exact path
$image->bytes();                      // raw binary
$image->base64();                     // base64 string
$image->dataUri();                    // data:image/png;base64,... for <img src>
$image->mimeType;                     // image/png

$response->store('images');           // shortcut for ->image()->store()
$response->images();                  // all returned images
$response->usage->totalTokens;        // token usage
$response->cost();                    // estimated cost in USD
```

Return an image straight from a route:

```php
Route::get('/cabin', fn () => Gemini::image('A cozy cabin')->generate()->image());
```

### Getting text with the image

```php
$response = Gemini::image('Design a logo for a coffee shop and explain it')
    ->withText()
    ->generate();

$response->text;   // the model's explanation
```

## Image editing and reference images

Attach one or more images. They are sent before the prompt, as Gemini recommends.

```php
Gemini::image('Change the sky to a starry night')
    ->withImage(storage_path('app/photo.jpg'))       // local path
    ->generate()
    ->store('edits');

Gemini::image('Put this product on a marble kitchen counter')
    ->withImage($request->file('product'))           // uploaded file
    ->withImageFromDisk('brand/logo.png', 's3')      // storage disk
    ->withImageUrl('https://example.com/style.jpg')  // remote URL
    ->withImageData($bytes, 'image/png')             // raw bytes
    ->generate();
```

Disk and URL images are read when the request is sent, so queued requests stay small.

## Multi-turn editing

Refine the same image step by step, like a conversation. Each message edits the result of the previous one.

```php
$chat = Gemini::imageChat()->aspectRatio('1:1');

$chat->send('A cozy cabin in the mountains')->store('cabin');
$chat->send('Add snow on the roof')->store('cabin');
$chat->send('Make it night with warm light in the windows')->store('cabin');
```

Attach images to a message with the usual methods. They go with the next message only:

```php
$chat->withImage($request->file('logo'))->send('Paint this logo on the cabin door');
```

Continue a conversation across HTTP requests by storing its history:

```php
session(['cabin-chat' => $chat->history()]);

// Later...
Gemini::imageChat()
    ->withHistory(session('cabin-chat'))
    ->send('Add a chimney with smoke');
```

The history contains the generated images, so store it somewhere that can hold a few MB per conversation, such as the cache, a file or a database column.

`send()` returns the response even when the model replies with text only (for example, to ask a question). Check `$response->images()`, or use `generate()` to throw when no image comes back.

## Batch generation

Generate many images at once. Requests run concurrently, and one failure doesn't stop the rest:

```php
$results = Gemini::batch([
    'mug'   => 'A white coffee mug on a wooden table, studio lighting',
    'shirt' => 'A black t-shirt on a hanger, studio lighting',
    'hat'   => Gemini::image('A red baseball cap')->aspectRatio('1:1'),
])->concurrency(5)->generate();

$paths = $results->store('catalog', 's3');   // ['mug' => 'catalog/...png', ...]

foreach ($results->failed() as $key => $exception) {
    logger()->warning("Could not generate {$key}: {$exception->getMessage()}");
}

$results->get('mug');       // ImageResponse or GeminiException
$results->successful();     // successful responses, keyed like the requests
$results->totalCost();      // estimated USD for the whole batch
```

Plain strings are image prompts. You can also mix in `Gemini::text()` requests, for example to write product descriptions in the same batch. Keep `concurrency()` within your Gemini rate limits.

For hundreds or thousands of images, use queued jobs with Laravel's [job batching](https://laravel.com/docs/queues#job-batching) instead:

```php
use Illuminate\Support\Facades\Bus;
use AnmolMishra\Gemini\Jobs\GenerateMedia;

Bus::batch(
    $products->map(fn ($product) => new GenerateMedia(
        Gemini::image("Studio photo of {$product->name}"),
        directory: 'catalog',
        meta: ['product_id' => $product->id],
    ))
)->name('Catalog images')->dispatch();
```

## Queues and events

Image generation takes several seconds and video takes minutes, so you'll usually want them off the request cycle. `queue()` generates in the background, stores the result and fires an event:

```php
Gemini::image('Studio photo of a white coffee mug')
    ->aspectRatio('1:1')
    ->queue('products', 's3', meta: ['product_id' => $product->id]);
```

Listen for the result:

```php
use AnmolMishra\Gemini\Events\GenerationFailed;
use AnmolMishra\Gemini\Events\ImageGenerated;

Event::listen(function (ImageGenerated $event) {
    Product::find($event->meta['product_id'])->update(['image' => $event->path]);

    // Also: $event->disk, $event->model, $event->text, $event->cost
});

Event::listen(function (GenerationFailed $event) {
    // $event->type ('image' or 'video'), $event->exception, $event->meta
});
```

Videos work the same way and fire `VideoGenerated`.

`queue()` returns Laravel's pending dispatch, so you can chain `->onQueue('ai')` or `->delay(...)`. Set a default connection and queue with `GEMINI_QUEUE_CONNECTION` and `GEMINI_QUEUE`. Jobs time out after 300 seconds for images and 900 for videos, so make sure your queue connection's `retry_after` is longer than that.

The API key is never written into the queued job. The worker reads it from its own config.

## Video generation

Videos are generated with Gemini Omni (`gemini-omni-1.1-flash`), and audio is included.

```php
$response = Gemini::video('Slow dolly shot of a lighthouse at dusk, waves crashing, no dialogue')
    ->aspectRatio('16:9')     // 16:9 (default) or 9:16
    ->resolution('1080p')     // 360p, 720p (default), 1080p or 4k
    ->generate();

$response->store('videos');   // videos/Xy3...mp4
```

Generation takes a few minutes, so in web apps use `->queue('videos')` instead of `generate()`.

There's no separate negative-prompt or audio option. Describe what you want, and what to avoid, in the prompt.

Animate an image:

```php
Gemini::video('The waves start moving and the clouds drift by')
    ->withImageFromDisk('photos/beach.jpg', 's3')
    ->generate()
    ->store('videos');
```

Edit or extend a video you generated earlier, using its interaction id:

```php
$first = Gemini::video('A paper boat floating down a stream')->generate();

Gemini::video('Continue: the boat drifts into a small waterfall')
    ->after($first->id)
    ->generate();
```

The video is returned as a file and downloaded when you first access it (`store()`, `save()`, `bytes()`). For very short videos, `->inline()` returns the bytes in the response instead.

## Text generation

```php
$response = Gemini::text('Write a tagline for a coffee shop')
    ->system('You are a witty copywriter.')
    ->temperature(0.8)
    ->maxTokens(200)
    ->generate();

echo $response;           // or $response->text()
```

### Vision

```php
Gemini::text('Describe this image in one sentence')
    ->withImage(storage_path('app/photo.jpg'))
    ->generate()
    ->text();
```

### Structured JSON

```php
$response = Gemini::text('Suggest 3 names for a coffee shop')
    ->asJson([
        'type' => 'array',
        'items' => ['type' => 'string'],
    ])
    ->generate();

$response->json();        // ['Bean There', 'Daily Grind', 'Brew Haven']
```

## Cost tracking

Every image and text response estimates its cost in USD from the token usage:

```php
$response = Gemini::image('A cozy cabin')->generate();

$response->cost();               // 0.06884
$response->usage->promptTokens;
$response->usage->outputTokens;
$response->usage->imageTokens;
$response->usage->thoughtTokens;
```

Prices live in the `pricing` section of `config/gemini.php`, in USD per million tokens. They come from Google's [pricing page](https://ai.google.dev/gemini-api/docs/pricing) as of October 2026. Google changes prices, so publish the config and keep them current. `cost()` returns `null` for models without a price. Add any model like this:

```php
'pricing' => [
    'my-model' => ['input' => 0.50, 'output' => 3.00, 'image_output' => 60.00],
],
```

Costs are estimates for the standard paid tier. Video cost isn't tracked yet.

## Advanced

Pass any `generationConfig` option that has no dedicated method:

```php
Gemini::image('A cabin')->withConfig(['seed' => 42])->generate();
```

Build conditionally:

```php
Gemini::image($prompt)
    ->when($ratio, fn ($request) => $request->aspectRatio($ratio))
    ->generate();
```

Send a raw payload with the low-level client:

```php
$data = Gemini::client()->generateContent('gemini-3.5-flash', [
    'contents' => [['parts' => [['text' => 'Hello']]]],
]);
```

Inspect the payload without sending it:

```php
Gemini::image('A cabin')->aspectRatio('1:1')->toPayload();
```

## Error handling

Every failure throws `AnmolMishra\Gemini\Exceptions\GeminiException`:

```php
use AnmolMishra\Gemini\Exceptions\GeminiException;

try {
    $image = Gemini::image($prompt)->generate()->image();
} catch (GeminiException $e) {
    $e->getMessage();      // readable error from the API
    $e->status;            // HTTP status, if any
    $e->isRateLimited();   // true on 429
    $e->response;          // the raw Laravel HTTP response, if any
}
```

It is thrown when:

- the API returns an error (after retries on 429 / 5xx)
- the prompt is blocked by safety filters
- an image or video request returns no image or video (for example `finishReason: SAFETY`)
- a generated video file fails to process
- the API key is missing

In batches, failures are collected in the results instead of being thrown.

## Artisan commands

```bash
php artisan gemini:image "A cozy cabin at sunset" --aspect-ratio=16:9 --size=2K --disk=public --dir=gemini
php artisan gemini:video "A lighthouse at dusk" --resolution=720p --image=storage/app/photo.jpg
```

## Testing your app

`Gemini::fake()` intercepts every Gemini API call. No real requests are sent and no credits are used.

```php
use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Testing\FakeResponse;

it('creates a product image', function () {
    Storage::fake('public');
    Gemini::fake([
        FakeResponse::image(),                  // 1st call returns a 1x1 PNG
        FakeResponse::text('A nice caption'),   // 2nd call returns text
        FakeResponse::video(),                  // 3rd call returns a fake video
        FakeResponse::error(429, 'Quota hit'),  // 4th call fails
    ]);

    // ... call your code ...

    Gemini::assertSent(fn (array $payload, string $model) =>
        $model === 'gemini-3.1-flash-image'
        && $payload['generationConfig']['imageConfig']['aspectRatio'] === '1:1'
    );
    Gemini::assertSentCount(1);
});
```

With no responses, `Gemini::fake()` answers image and text requests with fake text and an image, and video requests with a fake video. Faked requests are not retried. Also available: `assertNotSent()` and `assertNothingSent()`.

To test queued generation, use `Bus::fake()` and assert `GenerateMedia` was dispatched, or run the sync queue with `Event::fake()` and assert `ImageGenerated` was fired.

## Configuration

| Key | Env | Default |
| --- | --- | --- |
| `api_key` | `GEMINI_API_KEY` | none |
| `base_url` | `GEMINI_BASE_URL` | `https://generativelanguage.googleapis.com/v1beta` |
| `models.image` | `GEMINI_IMAGE_MODEL` | `gemini-3.1-flash-image` |
| `models.text` | `GEMINI_TEXT_MODEL` | `gemini-3.5-flash` |
| `models.video` | `GEMINI_VIDEO_MODEL` | `gemini-omni-1.1-flash` |
| `timeout` | `GEMINI_TIMEOUT` | `120` seconds |
| `retry.times` | `GEMINI_RETRY_TIMES` | `3` attempts |
| `retry.sleep` | `GEMINI_RETRY_SLEEP` | `1000` ms |
| `video.timeout` | `GEMINI_VIDEO_TIMEOUT` | `600` seconds |
| `disk` | `GEMINI_DISK` | default disk |
| `queue.connection` | `GEMINI_QUEUE_CONNECTION` | default connection |
| `queue.name` | `GEMINI_QUEUE` | default queue |
| `pricing` | none | see [Cost tracking](#cost-tracking) |

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Bug reports and pull requests are welcome.

## Security

Please report security issues privately. See [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
