<?php

namespace AnmolMishra\Gemini\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class GenerationFailed
{
    use Dispatchable;

    /**
     * @param  string  $type  "image" or "video"
     * @param  array  $meta  whatever you passed to queue()
     */
    public function __construct(
        public string $type,
        public string $model,
        public Throwable $exception,
        public array $meta = [],
    ) {}
}
