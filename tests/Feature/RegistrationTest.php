<?php

use Dwivedianuj9118\LaravelAiSarvam\SarvamProvider;
use Laravel\Ai\AiManager;

test('the sarvam driver resolves to the sarvam provider', function (): void {
    $provider = app(AiManager::class)->instance('sarvam');

    expect($provider)->toBeInstanceOf(SarvamProvider::class)
        ->and($provider->name())->toBe('sarvam')
        ->and($provider->defaultTextModel())->toBe('sarvam-105b')
        ->and($provider->defaultAudioModel())->toBe('bulbul:v3')
        ->and($provider->defaultTranscriptionModel())->toBe('saaras:v4')
        ->and($provider->defaultLanguage())->toBe('hi-IN');
});

test('default models and language may be configured', function (): void {
    config(['ai.providers.sarvam' => [
        'driver' => 'sarvam',
        'key' => 'test-key',
        'language' => 'ta-IN',
        'models' => [
            'text' => ['default' => 'sarvam-30b', 'smartest' => 'sarvam-105b'],
            'audio' => ['default' => 'bulbul:v2'],
            'transcription' => ['default' => 'saaras:v3'],
        ],
    ]]);

    $provider = app(AiManager::class)->instance('sarvam');

    expect($provider->defaultTextModel())->toBe('sarvam-30b')
        ->and($provider->cheapestTextModel())->toBe('sarvam-30b')
        ->and($provider->smartestTextModel())->toBe('sarvam-105b')
        ->and($provider->defaultAudioModel())->toBe('bulbul:v2')
        ->and($provider->defaultTranscriptionModel())->toBe('saaras:v3')
        ->and($provider->defaultLanguage())->toBe('ta-IN');
});
