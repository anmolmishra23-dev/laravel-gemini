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
        $this->timeout = $request instanceof VideoRequest ? 900 : 300;

        $this->onConnection(config('gemini.queue.connection'));
        $this->onQueue(config('gemini.queue.name'));
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
