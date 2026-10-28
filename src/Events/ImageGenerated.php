<?php

namespace AnmolMishra\Gemini\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImageGenerated
{
    use Dispatchable;

    /**
     * @param  array  $meta  whatever you passed to queue(), e.g. ['product_id' => 5]
     */
    public function __construct(
        public string $path,
        public ?string $disk,
        public string $model,
        public ?string $text,
        public ?float $cost,
        public array $meta = [],
    ) {}
}
