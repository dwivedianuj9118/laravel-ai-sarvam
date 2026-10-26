<?php

namespace Dwivedianuj9118\LaravelAiSarvam\Gateway;

use Dwivedianuj9118\LaravelAiSarvam\SarvamProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Files\TranscribableAudio;
use Laravel\Ai\Contracts\Gateway\AudioGateway;
use Laravel\Ai\Contracts\Gateway\TranscriptionGateway;
use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\Provider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Laravel\Ai\Gateway\Concerns\CreatesClient;
use Laravel\Ai\Gateway\Concerns\HandlesFailoverErrors;
use Laravel\Ai\Responses\AudioResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Responses\Data\TranscriptionUsage;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TranscriptionResponse;

class SarvamGateway implements AudioGateway, TranscriptionGateway
{
    use CreatesClient;
    use HandlesFailoverErrors;

    /**
     * The voices used for the SDK's default-male and default-female aliases, keyed by model.
     *
     * @var array<string, array{default-male: string, default-female: string}>
     */
    protected const DEFAULT_VOICES = [
        'bulbul:v2' => ['default-male' => 'abhilash', 'default-female' => 'anushka'],
        'bulbul:v3' => ['default-male' => 'shubh', 'default-female' => 'priya'],
    ];

    /**
     * The MIME types of Sarvam's output audio codecs.
     *
     * @var array<string, string>
     */
    protected const MIME_TYPES = [
        'wav' => 'audio/wav',
        'mp3' => 'audio/mpeg',
        'aac' => 'audio/aac',
        'flac' => 'audio/flac',
        'opus' => 'audio/ogg',
        'linear16' => 'audio/L16',
        'mulaw' => 'audio/basic',
        'alaw' => 'audio/basic',
    ];

    /**
     * Generate audio from the given text.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function generateAudio(
        AudioProvider $provider,
        string $model,
        string $text,
        string $voice,
        ?string $instructions = null,
        int $timeout = 30,
        array $providerOptions = [],
    ): AudioResponse {
        $body = array_merge(array_filter([
            'text' => $text,
            'model' => $model,
            'language_code' => $this->defaultLanguage($provider),
            'speaker' => $this->resolveVoice($provider, $model, $voice),
        ], fn (mixed $value): bool => $value !== null), $providerOptions);

        $response = $this->withErrorHandling($provider->name(), fn () => $this->client($provider, $timeout)
            ->post('text-to-speech', $body)
            ->throw());

        $codec = (string) ($body['output_audio_codec'] ?? 'wav');

        return new AudioResponse(
            (string) ($response->json('audios.0') ?? ''),
            new Usage,
            new Meta($provider->name(), $model),
            self::MIME_TYPES[$codec] ?? 'audio/wav',
        );
    }

    /**
     * Generate text from the given audio.
     *
     * Sarvam's REST endpoint does not identify speakers, so a diarized request returns
     * the timestamped chunks Sarvam provides as segments without a speaker.
     *
     * @param  array<string, mixed>  $providerOptions
     */
    public function generateTranscription(
        TranscriptionProvider $provider,
        string $model,
        TranscribableAudio $audio,
        ?string $language = null,
        bool $diarize = false,
        int $timeout = 30,
        array $providerOptions = [],
    ): TranscriptionResponse {
        $fields = array_merge([
            'model' => $model,
            'language_code' => $this->normalizeLanguage($language) ?? 'unknown',
        ], $diarize ? ['with_timestamps' => true] : [], $providerOptions);

        // The request is built inside the callback so a retried attempt attaches the file again...
        $response = $this->withErrorHandling($provider->name(), fn () => $this->client($provider, $timeout)
            ->attach('file', $audio->content(), 'audio', array_filter(['Content-Type' => $audio->mimeType()]))
            ->post('speech-to-text', array_map($this->multipartValue(...), $fields))
            ->throw());

        $data = $response->json();

        return new TranscriptionResponse(
            (string) ($data['transcript'] ?? ''),
            $diarize ? $this->segments($data['timestamps'] ?? []) : new Collection,
            new TranscriptionUsage,
            new Meta($provider->name(), $model),
        );
    }

    /**
     * Get the API root URL, which may be overridden with the provider's "url" option.
     */
    public static function rootUrl(Provider $provider): string
    {
        return rtrim($provider->additionalConfiguration()['url'] ?? 'https://api.sarvam.ai', '/');
    }

    /**
     * Resolve the SDK's default voice aliases to a Sarvam speaker for the given model.
     *
     * Unknown models fall back to Sarvam's own default speaker by omitting the field.
     */
    protected function resolveVoice(AudioProvider $provider, string $model, string $voice): ?string
    {
        if (! in_array($voice, ['default-male', 'default-female'], true)) {
            return $voice;
        }

        return $provider->additionalConfiguration()['voices'][$model][$voice]
            ?? self::DEFAULT_VOICES[$model][$voice]
            ?? null;
    }

    /**
     * Get the provider's default language code.
     */
    protected function defaultLanguage(Provider $provider): string
    {
        return $provider instanceof SarvamProvider
            ? $provider->defaultLanguage()
            : ($provider->additionalConfiguration()['language'] ?? 'hi-IN');
    }

    /**
     * Normalize a language such as "hi" or "hi-in" to Sarvam's "hi-IN" format.
     */
    protected function normalizeLanguage(?string $language): ?string
    {
        if (blank($language)) {
            return null;
        }

        if (preg_match('/^([a-z]{2,3})(?:[-_]([a-z]{2}))?$/i', $language, $matches) !== 1) {
            return $language;
        }

        return strtolower($matches[1]).'-'.strtoupper($matches[2] ?? 'IN');
    }

    /**
     * Convert a value into its multipart form representation.
     */
    protected function multipartValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    /**
     * Build transcription segments from Sarvam's chunk timestamps.
     *
     * @param  array{words?: list<string>, start_time_seconds?: list<float>, end_time_seconds?: list<float>}  $timestamps
     * @return Collection<int, TranscriptionSegment>
     */
    protected function segments(array $timestamps): Collection
    {
        return (new Collection($timestamps['words'] ?? []))->map(fn (string $text, int $index): TranscriptionSegment => new TranscriptionSegment(
            $text,
            '',
            (float) ($timestamps['start_time_seconds'][$index] ?? 0),
            (float) ($timestamps['end_time_seconds'][$index] ?? 0),
        ))->values();
    }

    /**
     * Get an HTTP client for Sarvam's speech APIs.
     */
    protected function client(Provider $provider, int $timeout = 30): PendingRequest
    {
        return $this->createClient(
            self::rootUrl($provider),
            array_filter(['api-subscription-key' => $provider->providerCredentials()['key'] ?? null]),
            $provider->additionalConfiguration()['headers'] ?? [],
            $timeout,
            false,
        );
    }
}
