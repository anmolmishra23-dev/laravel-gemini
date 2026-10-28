<?php

namespace AnmolMishra\Gemini\Requests;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Responses\ImageResponse;

/**
 * Multi-turn image editing: each message refines the images from earlier turns.
 */
class ImageChat extends ImageRequest
{
    protected array $history = [];

    /**
     * Send the next message. Images attached with withImage() go with this message only.
     *
     * Unlike generate(), this does not throw when the model answers with text only,
     * for example when it asks a clarifying question.
     *
     * @throws GeminiException
     */
    public function send(?string $prompt = null): ImageResponse
    {
        if ($prompt !== null) {
            $this->prompt($prompt);
        }

        $turn = $this->userTurn();
        $data = $this->client->generateContent($this->model, $this->buildPayload([...$this->history, $turn]));
        $response = ImageResponse::fromArray($data, $this->model);

        // Keep the model turn exactly as returned: Gemini needs its thought
        // signatures back to keep editing the same image.
        $reply = data_get($data, 'candidates.0.content');

        if (! empty($reply['parts'])) {
            $this->history[] = $turn;
            $this->history[] = ['role' => 'model'] + $reply;
        }

        $this->prompt = null;
        $this->images = [];

        return $response;
    }

    /**
     * Send the next message and require an image back.
     *
     * @throws GeminiException
     */
    public function generate(): ImageResponse
    {
        $response = $this->send();

        if ($response->images() === []) {
            throw GeminiException::noImageReturned($response->finishReason, $response->text);
        }

        return $response;
    }

    /**
     * The conversation so far. Store it (session, cache, database) to continue later.
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * Continue a conversation from a stored history.
     */
    public function withHistory(array $history): static
    {
        $this->history = $history;

        return $this;
    }

    public function reset(): static
    {
        $this->history = [];

        return $this;
    }

    public function toPayload(): array
    {
        return $this->buildPayload([...$this->history, $this->userTurn()]);
    }
}
