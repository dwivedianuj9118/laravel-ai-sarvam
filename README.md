# Sarvam AI for the Laravel AI SDK

A [Sarvam AI](https://www.sarvam.ai) provider for the [Laravel AI SDK](https://laravel.com/docs/ai-sdk). It adds chat, text-to-speech and speech-to-text in Indian languages, so you can use the AI SDK's standard `agent()`, `Audio` and `Transcription` APIs with `provider: 'sarvam'`.

| Feature | Sarvam API | Laravel AI SDK |
|---|---|---|
| Chat | `sarvam-105b` (default) | `agent()->prompt()`, `stream()`, tools, structured output |
| Text-to-speech | Bulbul (`bulbul:v3` default) | `Audio::of(...)->generate()` |
| Speech-to-text | Saaras (`saaras:v4` default) | `Transcription::...->generate()` |

## Installation

```bash
composer require dwivedianuj9118/laravel-ai-sarvam
```

Add a provider to your application's `config/ai.php` and set `SARVAM_API_KEY` in your `.env` file:

```php
'providers' => [
    // ...

    'sarvam' => [
        'driver' => 'sarvam',
        'key' => env('SARVAM_API_KEY'),
        'language' => 'hi-IN', // Default language for text-to-speech...
    ],
],
```

## Usage

### Chat

```php
use function Laravel\Ai\agent;

$response = agent()->prompt('आज का पंचांग संक्षेप में बताइए।', provider: 'sarvam');

echo $response->text;
```

Agents, tools, streaming and structured output work the same way as with other providers, because Sarvam's chat API follows the OpenAI chat completions format.

### Text-to-speech

```php
use Laravel\Ai\Audio;

$audio = Audio::of('शुभ प्रभात! आपका दिन मंगलमय हो।')->generate(provider: 'sarvam');

Storage::put('greeting.wav', base64_decode($audio->audio));
```

`female()` and `male()` choose a default speaker for the model (`priya` / `shubh` for `bulbul:v3`, `anushka` / `abhilash` for `bulbul:v2`). Pass any Sarvam speaker with `voice()`:

```php
Audio::of('नमस्ते')->voice('kavya')->generate(provider: 'sarvam');
```

Use provider options for another language or any other Sarvam field:

```php
Audio::of('வணக்கம்')
    ->withProviderOptions([
        'language_code' => 'ta-IN',
        'pace' => 1.1,
        'output_audio_codec' => 'mp3',
    ])
    ->generate(provider: 'sarvam');
```

### Speech-to-text

```php
use Laravel\Ai\Transcription;

$transcript = Transcription::fromPath(storage_path('call.mp3'))
    ->language('hi') // Optional. Normalized to "hi-IN"; Sarvam detects the language when omitted.
    ->generate(provider: 'sarvam');

echo $transcript->text;
```

Calling `diarize()` requests Sarvam's timestamps and returns them as segments. Sarvam's REST endpoint does not identify speakers, so each segment's speaker is empty.

Sarvam's REST speech-to-text endpoint is meant for audio under 30 seconds. Use Sarvam's batch API for longer recordings.

## Configuration

```php
'sarvam' => [
    'driver' => 'sarvam',
    'key' => env('SARVAM_API_KEY'),
    'url' => env('SARVAM_URL'), // Optional API root, defaults to https://api.sarvam.ai
    'language' => 'hi-IN',
    'reasoning_effort' => 'low', // Optional: low, medium, high, max, or null to disable reasoning
    'max_tokens' => 4096, // Optional default token limit for chat
    'models' => [
        'text' => ['default' => 'sarvam-105b', 'cheapest' => 'sarvam-30b'],
        'audio' => ['default' => 'bulbul:v3'],
        'transcription' => ['default' => 'saaras:v4'],
    ],
    'voices' => [
        'bulbul:v3' => ['default-male' => 'shubh', 'default-female' => 'priya'],
    ],
],
```

Sarvam updates its models often. Check [Sarvam's model list](https://docs.sarvam.ai) for the latest names.

### Reasoning and empty replies

`sarvam-105b` reasons before it answers, and the reasoning counts towards `max_tokens` (Sarvam's default is 2048). If a long reasoning step uses the whole limit, the reply text is empty. Set a lower `reasoning_effort`, disable reasoning with `null`, or raise `max_tokens` in the provider config. A token limit set on the agent itself takes precedence over `max_tokens`.

## Testing

```bash
composer test
```

## License

MIT. See [LICENSE.md](LICENSE.md).
