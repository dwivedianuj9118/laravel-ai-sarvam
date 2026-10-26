<?php

namespace Dwivedianuj9118\LaravelAiSarvam\Gateway;

use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;

/**
 * Sarvam's chat completions API follows the OpenAI format under the /v1 prefix.
 */
class SarvamTextGateway extends OpenAiCompatibleGateway
{
    /**
     * Build the request body, applying the provider's configured reasoning effort and token limit.
     *
     * Sarvam's models reason before answering, and the reasoning counts towards max_tokens,
     * so these defaults keep long reasoning from leaving no room for the answer.
     */
    protected function buildStepBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        StepContext $stepContext,
    ): array {
        $body = parent::buildStepBody($provider, $model, $instructions, $messages, $tools, $schema, $options, $stepContext);

        $config = $provider->additionalConfiguration();

        if (array_key_exists('reasoning_effort', $config) && ! array_key_exists('reasoning_effort', $body)) {
            $body['reasoning_effort'] = $config['reasoning_effort'];
        }

        if (isset($config['max_tokens']) && ! isset($body['max_tokens'])) {
            $body['max_tokens'] = $config['max_tokens'];
        }

        return $body;
    }

    /**
     * Get the base URL for Sarvam's chat completions API.
     */
    protected function baseUrl(Provider $provider): string
    {
        return SarvamGateway::rootUrl($provider).'/v1';
    }
}
