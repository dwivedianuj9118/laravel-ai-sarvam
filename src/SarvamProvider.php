<?php

namespace Dwivedianuj9118\LaravelAiSarvam;

use Dwivedianuj9118\LaravelAiSarvam\Gateway\SarvamGateway;
use Dwivedianuj9118\LaravelAiSarvam\Gateway\SarvamTextGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\AudioGateway;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Gateway\TranscriptionGateway;
use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Laravel\Ai\Providers\Concerns\GeneratesAudio;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\GeneratesTranscriptions;
use Laravel\Ai\Providers\Concerns\HasAudioGateway;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\HasTranscriptionGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

class SarvamProvider extends Provider implements AudioProvider, TextProvider, TranscriptionProvider
{
    use GeneratesAudio;
    use GeneratesText;
    use GeneratesTranscriptions;
    use HasAudioGateway;
    use HasTextGateway;
    use HasTranscriptionGateway;
    use StreamsText;

    public function __construct(protected array $config, protected Dispatcher $events)
    {
        //
    }

    /**
     * Get the provider's text gateway.
     */
    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new SarvamTextGateway($this->events);
    }

    /**
     * Get the provider's audio gateway.
     */
    public function audioGateway(): AudioGateway
    {
        return $this->audioGateway ??= new SarvamGateway;
    }

    /**
     * Get the provider's transcription gateway.
     */
    public function transcriptionGateway(): TranscriptionGateway
    {
        return $this->transcriptionGateway ??= new SarvamGateway;
    }

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default'] ?? 'sarvam-105b';
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the default audio (TTS) model.
     */
    public function defaultAudioModel(): string
    {
        return $this->config['models']['audio']['default'] ?? 'bulbul:v3';
    }

    /**
     * Get the name of the default transcription (STT) model.
     */
    public function defaultTranscriptionModel(): string
    {
        return $this->config['models']['transcription']['default'] ?? 'saaras:v4';
    }

    /**
     * Get the default BCP-47 language code, such as "hi-IN".
     */
    public function defaultLanguage(): string
    {
        return $this->config['language'] ?? 'hi-IN';
    }
}
