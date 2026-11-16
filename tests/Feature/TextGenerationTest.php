<?php

use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Testing\FakeResponse;

it('generates text', function () {
    Gemini::fake([FakeResponse::text('Hello from Gemini')]);

    $response = Gemini::text('Say hello')->generate();

    expect($response->text())->toBe('Hello from Gemini')
        ->and((string) $response)->toBe('Hello from Gemini')
        ->and($response->finishReason)->toBe('STOP')
        ->and($response->usage->promptTokens)->toBe(10);
});

it('sends the system instruction and generation config', function () {
    Gemini::fake();

    Gemini::text('Write a tagline')
        ->system('You are a copywriter.')
        ->temperature(0.4)
        ->maxTokens(100)
        ->generate();

    Gemini::assertSent(fn (array $payload) => $payload['systemInstruction'] === ['parts' => [['text' => 'You are a copywriter.']]]
        && $payload['generationConfig'] === ['temperature' => 0.4, 'maxOutputTokens' => 100]);
});

it('returns structured json', function () {
    $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];
    Gemini::fake([FakeResponse::text('{"title":"Cozy Cabin"}')]);

    $response = Gemini::text('Name this image')->asJson($schema)->generate();

    expect($response->json())->toBe(['title' => 'Cozy Cabin'])
        ->and($response->json('title'))->toBe('Cozy Cabin');

    Gemini::assertSent(fn (array $payload) => $payload['generationConfig'] === [
        'responseMimeType' => 'application/json',
        'responseSchema' => $schema,
    ]);
});

it('ignores thought parts', function () {
    Gemini::fake([FakeResponse::make([
        ['text' => 'Let me think about this...', 'thought' => true],
        ['text' => 'The answer is 42.'],
    ])]);

    expect(Gemini::text('What is the answer?')->generate()->text())->toBe('The answer is 42.');
});

it('accepts images for vision prompts', function () {
    Gemini::fake();

    Gemini::text('Describe this image')->withImageData(base64_decode(FakeResponse::PNG), 'image/png')->generate();

    Gemini::assertSent(fn (array $payload) => count($payload['contents'][0]['parts']) === 2);
});

it('allows raw generation config overrides', function () {
    Gemini::fake();

    Gemini::image('A cabin')->withConfig(['imageConfig' => ['aspectRatio' => '4:3'], 'seed' => 7])->generate();

    Gemini::assertSent(fn (array $payload) => $payload['generationConfig']['imageConfig'] === ['aspectRatio' => '4:3']
        && $payload['generationConfig']['seed'] === 7);
});

it('counts sent requests', function () {
    Gemini::fake();

    Gemini::assertNothingSent();

    Gemini::text('One')->generate();
    Gemini::text('Two')->generate();

    Gemini::assertSentCount(2);
});

it('uses a priced model by default', function () {
    Gemini::fake();

    expect(Gemini::text('Hi')->getModel())->toBe('gemini-3.8-flash')
        ->and(Gemini::text('Hi')->generate()->cost())->not->toBeNull();
});

it('sends safety settings and tools at the top level', function () {
    Gemini::fake();

    Gemini::text('Latest Laravel release?')
        ->safetySettings(['HARM_CATEGORY_HARASSMENT' => 'BLOCK_ONLY_HIGH'])
        ->tools([['googleSearch' => (object) []]])
        ->toolConfig(['functionCallingConfig' => ['mode' => 'AUTO']])
        ->temperature(0.2)
        ->generate();

    Gemini::assertSent(fn (array $payload) => $payload['safetySettings'] === [['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH']]
        && json_encode($payload['tools']) === '[{"googleSearch":{}}]'
        && $payload['toolConfig'] === ['functionCallingConfig' => ['mode' => 'AUTO']]
        && $payload['generationConfig'] === ['temperature' => 0.2]);
});

it('moves top-level fields out of withConfig', function () {
    $settings = [['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE']];

    $payload = Gemini::text('Hi')
        ->withConfig(['safetySettings' => $settings, 'tools' => [['codeExecution' => []]], 'topK' => 20])
        ->toPayload();

    expect($payload['safetySettings'])->toBe($settings)
        ->and($payload['tools'])->toBe([['codeExecution' => []]])
        ->and($payload['generationConfig'])->toBe(['topK' => 20]);
});

it('merges raw top-level fields with withPayload', function () {
    $payload = Gemini::text('Hi')->withPayload(['cachedContent' => 'cachedContents/abc'])->toPayload();

    expect($payload['cachedContent'])->toBe('cachedContents/abc')
        ->and($payload)->not->toHaveKey('generationConfig');
});
