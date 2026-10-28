# Changelog

All notable changes to this package are documented here.

## v1.1.0 - 2026-10-28

- `url()` and `temporaryUrl()` on saved images and videos, responses and the `ImageGenerated` / `VideoGenerated` events
- `path()` and `disk()` tell you where a file was saved
- Public or private saving: a `$visibility` argument on `store()` and `save()`, `storePublicly()`, and the `GEMINI_VISIBILITY` setting
- `queue()` accepts a `visibility` argument
- Batch results: `storePublicly()` and `urls()`
- `--public` option on the artisan commands, which now print the file's URL
- README guide for S3, Google Cloud Storage, Cloudflare R2, Azure and other buckets

## v1.0.0 - 2026-10-28

First stable release. Every feature has been tested against the live Gemini API: text and JSON output, image generation, multi-turn editing, reference-image editing, batches and video.

- Live test suite (`vendor/bin/pest --group=live`), skipped unless `GEMINI_LIVE_API_KEY` is set
- Before/after demo and typical costs in the README

## v0.2.0 - 2026-10-28

- Multi-turn image editing with `Gemini::imageChat()`, including saving and restoring the conversation
- Batch generation with `Gemini::batch()`: concurrent requests, per-item failures and `store()` for every image
- Queued generation with `->queue()`, the `GenerateMedia` job (works with `Bus::batch()`) and the `ImageGenerated`, `VideoGenerated` and `GenerationFailed` events
- Video generation with Gemini Omni: text-to-video, image-to-video, and editing or extending earlier videos
- Cost tracking with `$response->cost()` and a configurable pricing table
- `Usage` now reports image output tokens
- `gemini:video` artisan command
- Disk and URL images are read when the request is sent instead of when attached
- Fakes for video responses, plus `Gemini::assertNotSent()`
- Contributing guide, security policy and issue templates
- The package now requires `laravel/framework` instead of individual `illuminate/*` components

## v0.1.1 - 2026-10-28

- Drop Laravel 11 support. Every Laravel 11 release has unpatched security advisories, so Composer refuses to install it.

## v0.1.0 - 2026-10-28

- Image generation with aspect ratio and size options
- Image editing with reference images from paths, uploads, disks, URLs and raw bytes
- Text generation with system instructions, vision and structured JSON output
- Save generated images to any storage disk, or return them from routes
- Retries on connection errors, 429 and 5xx responses
- `Gemini::fake()` with request assertions
- `gemini:image` artisan command
