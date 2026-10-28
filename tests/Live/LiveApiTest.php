<?php

/*
 * Runs against the real Gemini API and costs money (roughly $0.30 plus one short video).
 *
 *   GEMINI_LIVE_API_KEY=your-key vendor/bin/pest --group=live
 *
 * Generated files and a cost log are written to GEMINI_LIVE_OUTPUT (default: build/live).
 */

use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Responses\ImageResponse;
use AnmolMishra\Gemini\Responses\TextResponse;

beforeEach(function () {
    $key = getenv('GEMINI_LIVE_API_KEY');

    if (! $key) {
        $this->markTestSkipped('Set GEMINI_LIVE_API_KEY to run live tests.');
    }

    $this->output = getenv('GEMINI_LIVE_OUTPUT') ?: dirname(__DIR__, 2).'/build/live';

    config([
        'gemini.api_key' => $key,
        'filesystems.disks.live' => ['driver' => 'local', 'root' => $this->output],
    ]);
});

function logCost(string $label, ?float $cost, string $extra = ''): void
{
    $line = sprintf("%-28s %s %s\n", $label, $cost === null ? 'n/a' : sprintf('$%.5f', $cost), $extra);

    file_put_contents(test()->output.'/costs.txt', $line, FILE_APPEND);
}

it('generates text and json', function () {
    $response = Gemini::text('Suggest 3 short names for a coffee shop.')
        ->asJson(['type' => 'array', 'items' => ['type' => 'string']])
        ->generate();

    expect($response->json())->toBeArray()->toHaveCount(3);
    logCost('text (json)', $response->cost(), json_encode($response->json()));
})->group('live');

it('generates an image', function () {
    $response = Gemini::image('A cozy wooden cabin in the mountains at golden hour, photorealistic')
        ->aspectRatio('16:9')
        ->generate();

    $path = $response->store('image', 'live');

    expect($response->images())->toHaveCount(1);
    logCost('image', $response->cost(), "{$path} tokens={$response->usage->imageTokens}");
})->group('live');

it('keeps editing the same image in a chat', function () {
    $chat = Gemini::imageChat()->aspectRatio('1:1');

    $first = $chat->prompt('A small red barn in a green field under a blue sky, photorealistic')->generate();
    $first->save('chat/before.png', 'live');
    logCost('chat turn 1', $first->cost());

    $second = $chat->prompt('Cover everything in deep snow and make it a winter evening. Keep the same barn and composition.')->generate();
    $second->save('chat/after.png', 'live');
    logCost('chat turn 2 (edit)', $second->cost());

    expect($chat->history())->toHaveCount(4);
})->group('live');

it('edits a reference image with nano banana 2.1', function () {
    if (! is_file($this->output.'/chat/before.png')) {
        $this->markTestSkipped('Needs the chat test to run first.');
    }

    $response = Gemini::image('Turn this into a watercolor painting.')
        ->model('gemini-nano-banana-2.1')
        ->withImage($this->output.'/chat/before.png')
        ->generate();

    expect($response->images())->toHaveCount(1);

    $response->save('reference/watercolor.png', 'live');
    logCost('reference edit (nb 2.1)', $response->cost());
})->group('live');

it('generates a batch', function () {
    $results = Gemini::batch([
        'mug' => Gemini::image('A white ceramic coffee mug, studio product photo')->model('gemini-3.1-flash-lite-image'),
        'plant' => Gemini::image('A small potted succulent, studio product photo')->model('gemini-3.1-flash-lite-image'),
        'caption' => Gemini::text('Write a one-line product caption for a white coffee mug.'),
    ])->concurrency(3)->generate();

    foreach ($results->failed() as $key => $exception) {
        logCost("batch {$key} FAILED", null, $exception->getMessage());
    }

    expect($results->hasFailures())->toBeFalse()
        ->and($results->get('mug'))->toBeInstanceOf(ImageResponse::class)
        ->and($results->get('caption'))->toBeInstanceOf(TextResponse::class);

    $results->store('batch', 'live');
    logCost('batch (2 images + text)', $results->totalCost(), (string) $results->get('caption'));
})->group('live');

it('generates a video', function () {
    $response = Gemini::video('A paper boat floating down a small stream in a forest, gentle water sounds, no dialogue')
        ->resolution('360p')
        ->generate();

    $path = $response->store('video', 'live');

    expect(filesize($this->output.'/'.$path))->toBeGreaterThan(10_000);
    logCost('video (360p)', null, "{$path} id={$response->id}");
})->group('live');
