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

    public function __construct(
        public ImageRequest|VideoRequest $request,
        public string $directory = 'gemini',
        public ?string $disk = null,
        public array $meta = [],
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
        $path = $response->store($this->directory, $this->disk);

        if ($this->request instanceof VideoRequest) {
            event(new VideoGenerated($path, $this->disk, $this->request->getModel(), $response->id, $this->meta));
        } else {
            event(new ImageGenerated($path, $this->disk, $this->request->getModel(), $response->text, $response->cost(), $this->meta));
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
