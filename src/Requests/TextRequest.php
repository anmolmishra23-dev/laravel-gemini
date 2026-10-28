<?php

namespace AnmolMishra\Gemini\Requests;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Responses\TextResponse;

class TextRequest extends GenerationRequest
{
    public function maxTokens(int $tokens): static
    {
        $this->generationConfig['maxOutputTokens'] = $tokens;

        return $this;
    }

    /**
     * Ask for a JSON response, optionally constrained by a response schema.
     */
    public function asJson(?array $schema = null): static
    {
        $this->generationConfig['responseMimeType'] = 'application/json';

        if ($schema !== null) {
            $this->generationConfig['responseSchema'] = $schema;
        }

        return $this;
    }

    /**
     * @throws GeminiException
     */
    public function generate(): TextResponse
    {
        return TextResponse::fromArray($this->send());
    }
}
