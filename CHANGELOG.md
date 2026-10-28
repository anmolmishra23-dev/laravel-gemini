# Changelog

All notable changes to this package are documented here.

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
