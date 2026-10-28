<?php

namespace AnmolMishra\Gemini\Responses;

use Stringable;

class TextResponse extends GeminiResponse implements Stringable
{
    public function text(): string
    {
        return $this->text ?? '';
    }

    /**
     * Decode a JSON response (see TextRequest::asJson()).
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = json_decode($this->text(), true);

        return $key === null ? $data : data_get($data, $key, $default);
    }

    public function __toString(): string
    {
        return $this->text();
    }
}
