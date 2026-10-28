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
