<?php

namespace AnmolMishra\Gemini\Events;

use Illuminate\Foundation\Events\Dispatchable;

class VideoGenerated
{
    use Dispatchable;

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
