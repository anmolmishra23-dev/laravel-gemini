<?php

use AnmolMishra\Gemini\Facades\Gemini;
use AnmolMishra\Gemini\Testing\FakeResponse;

it('calculates the cost of an image', function () {
    Gemini::fake([FakeResponse::make(
        [['inlineData' => ['mimeType' => 'image/png', 'data' => FakeResponse::PNG]]],
        usage: [
            'promptTokenCount' => 1000,
            'candidatesTokenCount' => 1300,
            'thoughtsTokenCount' => 200,
            'totalTokenCount' => 2500,
            'candidatesTokensDetails' => [
                ['modality' => 'IMAGE', 'tokenCount' => 1120],
                ['modality' => 'TEXT', 'tokenCount' => 180],
            ],
        ],
    )]);

    $response = Gemini::image('A cabin')->model('gemini-3.1-flash-image')->generate();

    // 1000 * 0.50 + (1300 - 1120 + 200) * 3.00 + 1120 * 60.00, per million tokens
    expect($response->usage->imageTokens)->toBe(1120)
        ->and($response->cost())->toBe(0.06884);
});

it('calculates the cost of text', function () {
    Gemini::fake([FakeResponse::make([['text' => 'Hi']], usage: [
        'promptTokenCount' => 2_000_000,
        'candidatesTokenCount' => 1_000_000,
        'totalTokenCount' => 3_000_000,
    ])]);

    expect(Gemini::text('Hi')->model('gemini-2.5-flash')->generate()->cost())->toBe(3.1);
});

it('returns null for models without a price', function () {
    Gemini::fake();

    expect(Gemini::text('Hi')->model('some-new-model')->generate()->cost())->toBeNull();
});

it('uses prices from the config', function () {
    config(['gemini.pricing.my-model' => ['input' => 1, 'output' => 1]]);
    Gemini::fake();

    // 10 prompt + 20 output tokens at $1 per million
    expect(Gemini::text('Hi')->model('my-model')->generate()->cost())->toBe(0.00003);
});

it('adds up the cost of a batch', function () {
    Gemini::fake([
        FakeResponse::make([['text' => 'A']], usage: ['promptTokenCount' => 1_000_000, 'candidatesTokenCount' => 0]),
        FakeResponse::make([['text' => 'B']], usage: ['promptTokenCount' => 1_000_000, 'candidatesTokenCount' => 0]),
    ]);

    $results = Gemini::batch([
        Gemini::text('A')->model('gemini-2.5-flash'),
        Gemini::text('B')->model('gemini-2.5-flash'),
    ])->concurrency(1)->generate();

    expect($results->totalCost())->toBe(0.6);
});
