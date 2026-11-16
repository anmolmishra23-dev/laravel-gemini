<?php

namespace AnmolMishra\Gemini\Jobs;

use AnmolMishra\Gemini\Events\GenerationFailed;
use AnmolMishra\Gemini\Events\ImageGenerated;
use AnmolMishra\Gemini\Events\VideoGenerated;
use AnmolMishra\Gemini\Requests\ImageRequest;
use AnmolMishra\Gemini\Requests\VideoRequest;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Generates an image or video, stores it and fires an event.
 * Works with Bus::batch() for large jobs like product catalogs.
 */
class GenerateMedia implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The client already retries rate limits and server errors.
     */
    public int $tries = 1;

    public int $timeout;

    /**
     * @param  string|null  $disk  any disk from config/filesystems.php, e.g. "s3" or "gcs"
     * @param  array  $meta  passed back to you in the event, e.g. ['product_id' => 5]
     * @param  string|null  $visibility  "public", "private", or null for the disk's default
     */
    public function __construct(
        public ImageRequest|VideoRequest $request,
        public string $directory = 'gemini',
        public ?string $disk = null,
        public array $meta = [],
        public ?string $visibility = null,
    ) {
        $this->timeout = static::timeoutFor($request);

        $this->onConnection(config('gemini.queue.connection'));
        $this->onQueue(config('gemini.queue.name'));
    }

    /**
     * The configured job timeout, or enough time for every HTTP attempt to time
     * out, the retry pauses, video polling and the download, plus a minute spare.
     */
    public static function timeoutFor(ImageRequest|VideoRequest $request): int
    {
        $video = $request instanceof VideoRequest;

        if ($configured = config('gemini.queue.timeout.'.($video ? 'video' : 'image'))) {
            return (int) $configured;
        }

        $times = max(1, (int) config('gemini.retry.times', 1));
        $pauses = (int) ceil(($times - 1) * (int) config('gemini.retry.sleep', 1000) / 1000);

        if (! $video) {
            return $times * (int) config('gemini.timeout', 120) + $pauses + 60;
        }

        $requestTimeout = (int) config('gemini.video.timeout', 600);

        return $times * $requestTimeout + $pauses + (int) config('gemini.video.poll_timeout', 600) + $requestTimeout + 60;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $response = $this->request->generate();
        $path = $response->store($this->directory, $this->disk, $this->visibility);

        if ($this->request instanceof VideoRequest) {
            $disk = $response->video()->disk();

            event(new VideoGenerated($path, $disk, $this->request->getModel(), $response->id, $this->meta));
        } else {
            $disk = $response->image()->disk();

            event(new ImageGenerated($path, $disk, $this->request->getModel(), $response->text, $response->cost(), $this->meta));
        }
    }

    public function failed(Throwable $exception): void
    {
        event(new GenerationFailed(
            $this->request instanceof VideoRequest ? 'video' : 'image',
            $this->request->getModel(),
            $exception,
            $this->meta,
        ));
    }
}
