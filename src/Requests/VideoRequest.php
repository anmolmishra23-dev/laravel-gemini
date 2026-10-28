<?php

namespace AnmolMishra\Gemini\Requests;

use AnmolMishra\Gemini\Concerns\HasImageInputs;
use AnmolMishra\Gemini\Concerns\SerializesWithoutClient;
use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\GeminiClient;
use AnmolMishra\Gemini\Jobs\GenerateMedia;
use AnmolMishra\Gemini\Responses\VideoResponse;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;

/**
 * Video generation through the Interactions API (Gemini Omni).
 */
class VideoRequest
{
    use Conditionable;
    use HasImageInputs;
    use SerializesWithoutClient;

    protected ?string $prompt = null;

    protected ?string $aspectRatio = null;

    protected ?string $resolution = null;

    protected ?string $previousInteraction = null;

    protected bool $inline = false;

    public function __construct(
        protected GeminiClient $client,
        protected string $model,
    ) {}

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Describe the video. Audio and things to avoid ("no dialogue") also go in the prompt.
     */
    public function prompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * "16:9" (default) or "9:16".
     */
    public function aspectRatio(string $ratio): static
    {
        $this->aspectRatio = $ratio;

        return $this;
    }

    /**
     * "360p", "720p" (default), "1080p" or "4k".
     */
    public function resolution(string $resolution): static
    {
        $this->resolution = $resolution;

        return $this;
    }

    /**
     * Edit or extend the video from an earlier response.
     */
    public function after(string $interactionId): static
    {
        $this->previousInteraction = $interactionId;

        return $this;
    }

    /**
     * Return the video bytes in the response instead of a file to download.
     * Only suitable for small videos (under 4MB).
     */
    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;

        return $this;
    }

    /**
     * @throws GeminiException
     */
    public function generate(): VideoResponse
    {
        return $this->toResponse($this->client->createInteraction($this->toPayload()));
    }

    /**
     * Generate on the queue, store the video in the directory and fire VideoGenerated.
     */
    public function queue(string $directory = 'gemini', ?string $disk = null, array $meta = []): PendingDispatch
    {
        return GenerateMedia::dispatch($this, $directory, $disk, $meta);
    }

    /**
     * @throws GeminiException when the response contains no video
     */
    public function toResponse(array $data): VideoResponse
    {
        $response = VideoResponse::fromArray($data, $this->model);

        if ($response->videos() === []) {
            throw GeminiException::noVideoReturned($response->text);
        }

        return $response;
    }

    /**
     * Build the interaction payload without sending it.
     */
    public function toPayload(): array
    {
        if ($this->prompt === null && $this->images === []) {
            throw new InvalidArgumentException('A Gemini video request needs a prompt or at least one image.');
        }

        $input = $this->prompt;

        if ($this->images !== []) {
            $input = array_map(
                fn (array $image) => ['type' => 'image', 'data' => $image['data'], 'mime_type' => $image['mimeType']],
                $this->resolveImages(),
            );

            if ($this->prompt !== null) {
                $input[] = ['type' => 'text', 'text' => $this->prompt];
            }
        }

        $payload = [
            'model' => $this->model,
            'input' => $input,
            'response_format' => array_filter([
                'type' => 'video',
                'aspect_ratio' => $this->aspectRatio,
                'resolution' => $this->resolution,
                'delivery' => $this->inline ? null : 'uri',
            ]),
        ];

        if ($this->previousInteraction !== null) {
            $payload['previous_interaction_id'] = $this->previousInteraction;
        }

        return $payload;
    }
}
