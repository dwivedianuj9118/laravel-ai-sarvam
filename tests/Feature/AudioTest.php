<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Audio;
use Laravel\Ai\Exceptions\RateLimitedException;

test('audio requests post text, model, language and the default female speaker', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('शुभ प्रभात')->generate(provider: 'sarvam');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.sarvam.ai/text-to-speech'
            && $request->hasHeader('api-subscription-key', 'test-key')
            && ! $request->hasHeader('Authorization')
            && $request['text'] === 'शुभ प्रभात'
            && $request['model'] === 'bulbul:v3'
            && $request['language_code'] === 'hi-IN'
            && $request['speaker'] === 'priya';
    });
});

test('the default male voice resolves per model', function (string $model, string $speaker): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('Hello')->male()->generate(provider: 'sarvam', model: $model);

    Http::assertSent(fn (Request $request): bool => $request['speaker'] === $speaker);
})->with([
    'bulbul v3' => ['bulbul:v3', 'shubh'],
    'bulbul v2' => ['bulbul:v2', 'abhilash'],
]);

test('bulbul v2 uses its own default female speaker', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('Hello')->generate(provider: 'sarvam', model: 'bulbul:v2');

    Http::assertSent(fn (Request $request): bool => $request['speaker'] === 'anushka');
});

test('unknown models omit the speaker so sarvam uses its own default', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('Hello')->generate(provider: 'sarvam', model: 'bulbul:v4-flash');

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('speaker', $request->data()));
});

test('a custom voice is passed through unchanged', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('Hello')->voice('kavya')->generate(provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => $request['speaker'] === 'kavya');
});

test('default voices may be configured per model', function (): void {
    config(['ai.providers.sarvam.voices' => ['bulbul:v3' => ['default-female' => 'ritu']]]);

    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('Hello')->generate(provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => $request['speaker'] === 'ritu');
});

test('the configured language is used by default', function (): void {
    config(['ai.providers.sarvam.language' => 'mr-IN']);

    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('नमस्कार')->generate(provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => $request['language_code'] === 'mr-IN');
});

test('provider options override the language and request options', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    Audio::of('வணக்கம்')
        ->withProviderOptions(['language_code' => 'ta-IN', 'pace' => 1.2, 'output_audio_codec' => 'mp3'])
        ->generate(provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => $request['language_code'] === 'ta-IN'
        && $request['pace'] === 1.2
        && $request['output_audio_codec'] === 'mp3');
});

test('the response contains the base64 audio and its mime type', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse('wav-bytes')]);

    $response = Audio::of('Hello')->generate(provider: 'sarvam');

    expect($response->audio)->toBe(base64_encode('wav-bytes'))
        ->and($response->mimeType())->toBe('audio/wav')
        ->and($response->meta->provider)->toBe('sarvam')
        ->and($response->meta->model)->toBe('bulbul:v3');
});

test('the mime type follows the requested codec', function (): void {
    Http::fake(['*' => fakeSarvamAudioResponse()]);

    $response = Audio::of('Hello')
        ->withProviderOptions(['output_audio_codec' => 'mp3'])
        ->generate(provider: 'sarvam');

    expect($response->mimeType())->toBe('audio/mpeg');
});

test('rate limited responses throw a rate limited exception', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Quota exceeded', 'code' => 'insufficient_quota_error']], 429)]);

    Audio::of('Hello')->generate(provider: 'sarvam');
})->throws(RateLimitedException::class);
