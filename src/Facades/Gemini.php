<?php

namespace AnmolMishra\Gemini\Facades;

use AnmolMishra\Gemini\Gemini as GeminiService;
use AnmolMishra\Gemini\GeminiClient;
use AnmolMishra\Gemini\Testing\FakeResponse;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * @method static \AnmolMishra\Gemini\Requests\ImageRequest image(?string $prompt = null)
 * @method static \AnmolMishra\Gemini\Requests\TextRequest text(?string $prompt = null)
 * @method static \AnmolMishra\Gemini\GeminiClient client()
 *
 * @see GeminiService
 */
class Gemini extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeminiService::class;
    }

    /**
     * Fake Gemini API responses. Responses are returned in order; once they run
     * out, every request gets a default response containing text and an image.
     *
     * @param  array<int, array|PromiseInterface>  $responses
     */
    public static function fake(array $responses = []): void
    {
        $config = static::$app['config'];

        // A faked error should surface immediately instead of being retried.
        $config->set('gemini.retry.times', 1);

        if (blank($config->get('gemini.api_key'))) {
            $config->set('gemini.api_key', 'fake-key');
        }

        static::$app->forgetInstance(GeminiClient::class);
        static::$app->forgetInstance(GeminiService::class);
        static::clearResolvedInstance(GeminiService::class);

        $sequence = Http::sequence();

        foreach ($responses as $response) {
            is_array($response) ? $sequence->push($response) : $sequence->pushResponse($response);
        }

        $sequence->whenEmpty(Http::response(FakeResponse::default()));

        Http::fake([rtrim($config->get('gemini.base_url'), '/').'/*' => $sequence]);
    }

    /**
     * Assert a request was sent whose payload and model pass the callback.
     *
     * @param  callable(array $payload, string $model): bool  $callback
     */
    public static function assertSent(callable $callback): void
    {
        PHPUnit::assertTrue(
            static::recorded($callback)->isNotEmpty(),
            'An expected Gemini request was not sent.'
        );
    }

    public static function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, static::recorded());
    }

    public static function assertNothingSent(): void
    {
        static::assertSentCount(0);
    }

    protected static function recorded(?callable $callback = null): Collection
    {
        return Http::recorded(function (Request $request) use ($callback) {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            return $callback === null
                || $callback($request->data(), Str::between($request->url(), '/models/', ':generateContent'));
        });
    }
}
