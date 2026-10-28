<?php

namespace AnmolMishra\Gemini\Concerns;

use AnmolMishra\Gemini\GeminiClient;

/**
 * Lets requests be queued without writing the client (and its API key) into the job payload.
 */
trait SerializesWithoutClient
{
    public function __serialize(): array
    {
        $data = get_object_vars($this);

        unset($data['client']);

        return $data;
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->{$property} = $value;
        }

        $this->client = app(GeminiClient::class);
    }
}
