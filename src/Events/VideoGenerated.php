<?php

namespace AnmolMishra\Gemini\Events;

use AnmolMishra\Gemini\Concerns\LinksToStoredFile;
use Illuminate\Foundation\Events\Dispatchable;

class VideoGenerated
{
    use Dispatchable;
    use LinksToStoredFile;

    /**
     * @param  ?string  $interactionId  pass to Gemini::video()->after() to edit or extend this video
     * @param  array  $meta  whatever you passed to queue()
     */
    public function __construct(
        public string $path,
        public ?string $disk,
        public string $model,
        public ?string $interactionId,
        public array $meta = [],
    ) {}
}
