# Laravel Gemini

[![Tests](https://github.com/anmolmishra23-dev/laravel-gemini/actions/workflows/tests.yml/badge.svg)](https://github.com/anmolmishra23-dev/laravel-gemini/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/anmolmishra23-dev/laravel-gemini.svg)](https://packagist.org/packages/anmolmishra23-dev/laravel-gemini)
[![Total Downloads](https://img.shields.io/packagist/dt/anmolmishra23-dev/laravel-gemini.svg)](https://packagist.org/packages/anmolmishra23-dev/laravel-gemini)
[![License](https://img.shields.io/packagist/l/anmolmishra23-dev/laravel-gemini.svg)](LICENSE)

An unofficial Laravel SDK for Google Gemini, built for **image generation and image editing** first, with text generation included.

```php
use AnmolMishra\Gemini\Facades\Gemini;

Gemini::image('A cozy cabin in snowy mountains at sunset')
    ->aspectRatio('16:9')
    ->generate()
    ->store('images'); // images/Xy3...png
```

- Fluent API for image generation and editing (single or multiple reference images)
- Save straight to any Laravel storage disk (local, S3, ...)
- Return images directly from routes
- Text generation, system instructions and structured JSON output
- Automatic retries on 429 / 5xx, with clear exceptions
- `Gemini::fake()` and assertions for testing your app
- `php artisan gemini:image` for quick experiments

> This package is not affiliated with or endorsed by Google.

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
```

Return an image straight from a route:

```php
Route::get('/cabin', fn () => Gemini::image('A cozy cabin')->generate()->image());
```

### Image editing and reference images

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

### Getting text with the image

```php
$response = Gemini::image('Design a logo for a coffee shop and explain it')
    ->withText()
    ->generate();

$response->text;   // the model's explanation
```

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
- an image request returns no image (for example `finishReason: SAFETY`)
- the API key is missing

## Artisan command

```bash
php artisan gemini:image "A cozy cabin at sunset" --aspect-ratio=16:9 --size=2K --disk=public --dir=gemini
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
        FakeResponse::error(429, 'Quota hit'),  // 3rd call fails
    ]);

    // ... call your code ...

    Gemini::assertSent(fn (array $payload, string $model) =>
        $model === 'gemini-3.1-flash-image'
        && $payload['generationConfig']['imageConfig']['aspectRatio'] === '1:1'
    );
    Gemini::assertSentCount(1);
});
```

With no responses, `Gemini::fake()` answers every request with fake text and an image. Faked requests are not retried.

## Configuration

| Key | Env | Default |
| --- | --- | --- |
| `api_key` | `GEMINI_API_KEY` | none |
| `base_url` | `GEMINI_BASE_URL` | `https://generativelanguage.googleapis.com/v1beta` |
| `models.image` | `GEMINI_IMAGE_MODEL` | `gemini-3.1-flash-image` |
| `models.text` | `GEMINI_TEXT_MODEL` | `gemini-3.5-flash` |
| `timeout` | `GEMINI_TIMEOUT` | `120` seconds |
| `retry.times` | `GEMINI_RETRY_TIMES` | `3` attempts |
| `retry.sleep` | `GEMINI_RETRY_SLEEP` | `1000` ms |
| `disk` | `GEMINI_DISK` | default disk |

## Contributing

```bash
composer install
composer test     # Pest
composer format   # Pint
```

Pull requests are welcome. Please add tests for new features.

## License

MIT. See [LICENSE](LICENSE).
