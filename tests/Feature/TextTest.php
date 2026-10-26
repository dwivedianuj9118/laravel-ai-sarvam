<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Laravel\Ai\agent;

test('prompts are sent to the sarvam chat completions endpoint with a bearer token', function (): void {
    Http::fake(['*' => fakeSarvamChatResponse('नमस्ते! मैं आपकी कैसे मदद कर सकता हूँ?')]);

    $response = agent()->prompt('नमस्ते', provider: 'sarvam');

    expect($response->text)->toBe('नमस्ते! मैं आपकी कैसे मदद कर सकता हूँ?');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.sarvam.ai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'sarvam-105b';
    });
});

test('the model may be passed explicitly', function (): void {
    Http::fake(['*' => fakeSarvamChatResponse()]);

    agent()->prompt('Hello', provider: 'sarvam', model: 'sarvam-30b');

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'sarvam-30b');
});

test('usage is reported from the response', function (): void {
    Http::fake(['*' => fakeSarvamChatResponse()]);

    $response = agent()->prompt('Hello', provider: 'sarvam');

    expect($response->usage->inputTokens)->toBe(12)
        ->and($response->usage->outputTokens)->toBe(3);
});

test('a custom url replaces the api root for chat requests', function (): void {
    config(['ai.providers.sarvam.url' => 'https://proxy.example.com/sarvam/']);

    Http::fake(['*' => fakeSarvamChatResponse()]);

    agent()->prompt('Hello', provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://proxy.example.com/sarvam/v1/chat/completions');
});

test('reasoning effort and max tokens are sent from the provider config', function (): void {
    config(['ai.providers.sarvam.reasoning_effort' => 'low', 'ai.providers.sarvam.max_tokens' => 4096]);

    Http::fake(['*' => fakeSarvamChatResponse()]);

    agent()->prompt('Hello', provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => $request['reasoning_effort'] === 'low'
        && $request['max_tokens'] === 4096);
});

test('a null reasoning effort disables reasoning', function (): void {
    config(['ai.providers.sarvam.reasoning_effort' => null]);

    Http::fake(['*' => fakeSarvamChatResponse()]);

    agent()->prompt('Hello', provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => array_key_exists('reasoning_effort', $request->data())
        && $request['reasoning_effort'] === null);
});

test('reasoning effort and max tokens are omitted when not configured', function (): void {
    Http::fake(['*' => fakeSarvamChatResponse()]);

    agent()->prompt('Hello', provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('reasoning_effort', $request->data())
        && ! array_key_exists('max_tokens', $request->data()));
});
