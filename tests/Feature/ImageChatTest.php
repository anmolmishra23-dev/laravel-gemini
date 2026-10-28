<?php

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Testing\FakeResponse;

function imageWithSignature(string $signature): array
{
    return FakeResponse::make([
        ['inlineData' => ['mimeType' => 'image/png', 'data' => FakeResponse::PNG], 'thoughtSignature' => $signature],
    ]);
}

it('keeps editing the same image across turns', function () {
    Gemini::fake([imageWithSignature('sig-1'), imageWithSignature('sig-2')]);

    $chat = Gemini::imageChat()->aspectRatio('1:1');

    expect($chat->send('A cozy cabin')->images())->toHaveCount(1);
    $chat->send('Add snow on the roof');

    Gemini::assertSent(function (array $payload) {
        $contents = $payload['contents'];

        return count($contents) === 3
            && $contents[0] === ['role' => 'user', 'parts' => [['text' => 'A cozy cabin']]]
            && $contents[1]['role'] === 'model'
            && $contents[1]['parts'][0]['thoughtSignature'] === 'sig-1'
            && $contents[2] === ['role' => 'user', 'parts' => [['text' => 'Add snow on the roof']]]
            && $payload['generationConfig']['imageConfig'] === ['aspectRatio' => '1:1'];
    });

    expect($chat->history())->toHaveCount(4);
});

it('sends attached images with the next message only', function () {
    Gemini::fake();

    $chat = Gemini::imageChat();
    $chat->withImageData(base64_decode(FakeResponse::PNG), 'image/png')->send('Make it blue');
    $chat->send('Now make it red');

    Gemini::assertSent(fn (array $payload) => count($payload['contents']) === 3
        && count($payload['contents'][0]['parts']) === 2
        && $payload['contents'][2]['parts'] === [['text' => 'Now make it red']]);
});

it('continues a stored conversation', function () {
    Gemini::fake();

    $chat = Gemini::imageChat();
    $chat->send('A cabin');
    $history = $chat->history();

    Gemini::imageChat()->withHistory($history)->send('Make it night');

    Gemini::assertSent(fn (array $payload) => count($payload['contents']) === 3
        && $payload['contents'][0]['parts'] === [['text' => 'A cabin']]);
});

it('allows text-only answers from send but not from generate', function () {
    Gemini::fake([FakeResponse::text('Which style do you want?'), FakeResponse::text('Still no image')]);

    $chat = Gemini::imageChat();

    expect($chat->send('Draw something')->text)->toBe('Which style do you want?')
        ->and($chat->history())->toHaveCount(2);

    $chat->prompt('Anything')->generate();
})->throws(GeminiException::class, 'did not return an image');

it('leaves the history unchanged when a request fails', function () {
    Gemini::fake([FakeResponse::error(500, 'Boom')]);

    $chat = Gemini::imageChat();

    try {
        $chat->send('A cabin');
    } catch (GeminiException) {
    }

    expect($chat->history())->toBe([]);
});
