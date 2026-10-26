<?php

namespace Dwivedianuj9118\LaravelAiSarvam;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;

class SarvamServiceProvider extends ServiceProvider
{
    /**
     * Register the Sarvam driver with the Laravel AI SDK.
     */
    public function register(): void
    {
        $this->callAfterResolving(AiManager::class, function (AiManager $manager): void {
            $manager->extend('sarvam', function ($app, array $config): SarvamProvider {
                return new SarvamProvider($config, $app->make(Dispatcher::class));
            });
        });
    }
}
