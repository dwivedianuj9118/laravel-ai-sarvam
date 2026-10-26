<?php

use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;

use function Laravel\Ai\agent;

/*
 * Calls the real Sarvam API. Skipped unless SARVAM_API_KEY is set:
 *
 *     SARVAM_API_KEY=your-key vendor/bin/pest tests/Live
 *
 * Generated audio is written to live-output/ so you can listen to it.
 */

beforeEach(function (): void {
    $key = getenv('SARVAM_API_KEY') ?: null;

    if (blank($key)) {
        $this->markTestSkipped('Set SARVAM_API_KEY to run the live Sarvam tests.');
    }

    config(['ai.providers.sarvam.key' => $key]);

    @mkdir(dirname(__DIR__, 2).'/live-output');
});

function liveOutput(string $file): string
{
    return dirname(__DIR__, 2).'/live-output/'.$file;
}

function liveReport(string $label, string $value): void
{
    fwrite(STDERR, "\n[{$label}] {$value}\n");
}

test('chat replies in hindi', function (): void {
    $response = agent()->prompt('एक वाक्य में बताइए: दीपावली क्यों मनाई जाती है?', provider: 'sarvam');

    liveReport('chat', $response->text);
    liveReport('chat usage', "{$response->usage->inputTokens} in / {$response->usage->outputTokens} out, reasoning ".mb_strlen($response->reasoning ?? '').' chars');

    expect($response->text)->not->toBeEmpty();
});

test('chat replies with each reasoning effort', function (?string $effort): void {
    config(['ai.providers.sarvam.reasoning_effort' => $effort, 'ai.providers.sarvam.max_tokens' => 4096]);

    $response = agent()->prompt('एक वाक्य में बताइए: दीपावली क्यों मनाई जाती है?', provider: 'sarvam');

    liveReport('chat effort='.($effort ?? 'null'), $response->text);
    liveReport('chat effort='.($effort ?? 'null').' usage', "{$response->usage->outputTokens} out, reasoning ".mb_strlen($response->reasoning ?? '').' chars');

    expect($response->text)->not->toBeEmpty();
})->with(['low' => 'low', 'disabled' => null]);

test('text to speech generates hindi audio', function (): void {
    $response = Audio::of('नमस्ते! यह लारवेल से सर्वम एआई का परीक्षण है।')->generate(provider: 'sarvam');

    $bytes = base64_decode($response->audio);
    file_put_contents(liveOutput('hindi.wav'), $bytes);

    liveReport('tts hindi', strlen($bytes).' bytes, '.$response->mimeType().' -> live-output/hindi.wav');

    expect($bytes)->toStartWith('RIFF');
});

test('text to speech generates tamil audio with a male voice', function (): void {
    $response = Audio::of('வணக்கம், இது ஒரு சோதனை.')
        ->male()
        ->withProviderOptions(['language_code' => 'ta-IN'])
        ->generate(provider: 'sarvam');

    $bytes = base64_decode($response->audio);
    file_put_contents(liveOutput('tamil.wav'), $bytes);

    liveReport('tts tamil', strlen($bytes).' bytes -> live-output/tamil.wav');

    expect($bytes)->toStartWith('RIFF');
});

test('speech to text transcribes the generated hindi audio', function (): void {
    $audio = Audio::of('आज का मौसम बहुत अच्छा है।')->generate(provider: 'sarvam');

    $transcript = Transcription::fromBase64($audio->audio, 'audio/wav')
        ->language('hi')
        ->generate(provider: 'sarvam');

    liveReport('stt', $transcript->text);

    expect($transcript->text)->not->toBeEmpty();
});
