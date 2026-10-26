<?php

namespace Dwivedianuj9118\LaravelAiSarvam\Tests;

use Dwivedianuj9118\LaravelAiSarvam\SarvamServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            SarvamServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai.providers.sarvam', [
            'driver' => 'sarvam',
            'key' => 'test-key',
        ]);
    }
}
