<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Transcription;

test('transcription requests post the audio file and model to speech-to-text', function (): void {
    Http::fake(['*' => fakeSarvamTranscriptionResponse()]);

    $response = Transcription::fromBase64(base64_encode('fake-audio'), 'audio/wav')->generate(provider: 'sarvam');

    expect($response->text)->toBe('आज का पंचांग')
        ->and($response->segments)->toBeEmpty()
        ->and($response->meta->model)->toBe('saaras:v4');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.sarvam.ai/speech-to-text'
        && $request->isMultipart()
        && $request->hasHeader('api-subscription-key', 'test-key')
        && multipartField($request, 'file') === 'fake-audio'
        && multipartField($request, 'model') === 'saaras:v4'
        && multipartField($request, 'language_code') === 'unknown');
});

test('short and lowercase languages are normalized', function (string $language, string $expected): void {
    Http::fake(['*' => fakeSarvamTranscriptionResponse()]);

    Transcription::fromBase64(base64_encode('fake-audio'), 'audio/wav')
        ->language($language)
        ->generate(provider: 'sarvam');

    Http::assertSent(fn (Request $request): bool => multipartField($request, 'language_code') === $expected);
})->with([
    'short' => ['hi', 'hi-IN'],
    'lowercase region' => ['ta-in', 'ta-IN'],
    'already formatted' => ['bn-IN', 'bn-IN'],
    'three letter code' => ['kok', 'kok-IN'],
    'auto detect' => ['unknown', 'unknown'],
]);

test('diarized requests ask for timestamps and return them as segments', function (): void {
    Http::fake(['*' => fakeSarvamTranscriptionResponse('नमस्ते दुनिया', [
        'timestamps' => [
            'words' => ['नमस्ते', 'दुनिया'],
            'start_time_seconds' => [0.0, 0.8],
            'end_time_seconds' => [0.7, 1.5],
        ],
    ])]);

    $response = Transcription::fromBase64(base64_encode('fake-audio'), 'audio/wav')
        ->diarize()
        ->generate(provider: 'sarvam');

    expect($response->segments)->toHaveCount(2)
        ->and($response->segments[1]->text)->toBe('दुनिया')
        ->and($response->segments[1]->startSeconds)->toBe(0.8)
        ->and($response->segments[1]->endSeconds)->toBe(1.5);

    Http::assertSent(fn (Request $request): bool => multipartField($request, 'with_timestamps') === 'true');
});

test('provider options are sent as multipart fields', function (): void {
    Http::fake(['*' => fakeSarvamTranscriptionResponse()]);

    Transcription::fromBase64(base64_encode('fake-audio'), 'audio/wav')
        ->withProviderOptions(['mode' => 'translit', 'keyterms' => ['पंचांग', 'Lucknow']])
        ->generate(provider: 'sarvam', model: 'saaras:v3');

    Http::assertSent(fn (Request $request): bool => multipartField($request, 'model') === 'saaras:v3'
        && multipartField($request, 'mode') === 'translit'
        && multipartField($request, 'keyterms') === '["पंचांग","Lucknow"]');
});
