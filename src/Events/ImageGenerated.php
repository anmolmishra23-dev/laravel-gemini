<?php

namespace AnmolMishra\Gemini\Events;

use AnmolMishra\Gemini\Concerns\LinksToStoredFile;
use Illuminate\Foundation\Events\Dispatchable;

class ImageGenerated
{
    use Dispatchable;
    use LinksToStoredFile;

    /**
     * @param  string  $path  where the image was saved
     * @param  ?string  $disk  the disk it was saved to (null means the default disk)
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
